<?php

namespace App\Domain\Backup;

use App\Domain\Reporting\MetricsCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Section 3.2 / étape 10 — restauration d'une sauvegarde produite par
 * {@see DatabaseBackup}. Opération DESTRUCTIVE : chaque table sauvegardée est
 * vidée puis rechargée. Testée bout en bout (BackupRestoreTest).
 *
 * On vide TOUTES les tables avant d'en réinsérer AUCUNE : la cohérence des clés
 * étrangères n'est vraie qu'à la fin, d'où la mise en sommeil des contraintes le
 * temps de l'opération (adaptée au pilote : MySQL / SQLite).
 */
final class DatabaseRestore
{
    /** @return array<string, mixed> le manifeste restauré */
    public function executer(string $dossier): array
    {
        $dossier = rtrim($dossier, '/\\');
        $manifestPath = $dossier.'/manifest.json';

        if (! File::exists($manifestPath)) {
            throw new RuntimeException("Sauvegarde invalide : manifeste introuvable dans {$dossier}.");
        }

        $manifest = json_decode(File::get($manifestPath), true);
        $tables = array_values(array_filter(
            array_keys($manifest['tables'] ?? []),
            fn ($t) => Schema::hasTable($t),
        ));

        $driver = DB::getDriverName();

        DB::transaction(function () use ($tables, $dossier, $driver) {
            match ($driver) {
                'mysql', 'mariadb' => DB::statement('SET FOREIGN_KEY_CHECKS=0'),
                'sqlite' => DB::statement('PRAGMA defer_foreign_keys = ON'),
                default => null,
            };

            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
            foreach ($tables as $table) {
                $this->chargerTable($table, $dossier.'/tables/'.$table.'.jsonl');
            }

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        $this->restaurerImports($dossier.'/imports');

        // Les données ont changé : tous les agrégats en cache sont obsolètes.
        MetricsCache::bump();

        return $manifest;
    }

    private function chargerTable(string $table, string $fichier): void
    {
        if (! File::exists($fichier)) {
            return;
        }

        $lignes = [];
        foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
            $lignes[] = json_decode($ligne, true);
            if (count($lignes) === 200) {
                DB::table($table)->insert($lignes);
                $lignes = [];
            }
        }
        if ($lignes !== []) {
            DB::table($table)->insert($lignes);
        }
    }

    private function restaurerImports(string $source): void
    {
        $disque = Storage::disk(config('backup.disque_imports', 'local'));
        $dossier = config('backup.dossier_imports', 'imports');

        $disque->deleteDirectory($dossier);

        if (! File::isDirectory($source)) {
            return;
        }

        foreach (File::allFiles($source) as $fichier) {
            $disque->put($dossier.'/'.str_replace('\\', '/', $fichier->getRelativePathname()), File::get($fichier->getPathname()));
        }
    }
}
