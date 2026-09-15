<?php

namespace App\Domain\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Section 3.2 / étape 10 — sauvegarde LOGIQUE et portable.
 *
 * Produit un dossier horodaté :
 *   backups/AAAAMMJJ_HHMMSS[_label]/
 *     ├── manifest.json          (métadonnées + décompte par table)
 *     ├── tables/<table>.jsonl   (une ligne JSON par enregistrement)
 *     └── imports/…              (copie des fichiers sources archivés à l'import)
 *
 * Aucun outil externe : le même code tourne en local, en test et en production.
 * {@see DatabaseRestore} pour l'opération inverse (testée bout en bout).
 */
final class DatabaseBackup
{
    public function __construct(private readonly ?string $racine = null) {}

    /**
     * @return array{chemin:string, horodatage:string, manifest:array<string,mixed>}
     */
    public function executer(?string $label = null): array
    {
        $racine = rtrim($this->racine ?? config('backup.racine'), '/\\');
        $base = Carbon::now()->format('Ymd_His').($label ? '_'.preg_replace('/[^A-Za-z0-9_-]/', '', $label) : '');

        $nom = $base;
        $i = 1;
        while (File::isDirectory($racine.DIRECTORY_SEPARATOR.$nom)) {
            $nom = $base.'-'.$i++;
        }
        $chemin = $racine.DIRECTORY_SEPARATOR.$nom;

        File::ensureDirectoryExists($chemin.'/tables');

        $comptes = [];
        foreach ($this->tables() as $table) {
            $comptes[$table] = $this->exporterTable($table, $chemin.'/tables/'.$table.'.jsonl');
        }

        $fichiersImports = $this->copierImports($chemin.'/imports');

        $manifest = [
            'cree_le' => Carbon::now()->toIso8601String(),
            'label' => $label,
            'application' => config('app.name'),
            'pilote_bdd' => DB::getDriverName(),
            'tables' => $comptes,
            'fichiers_imports' => $fichiersImports,
        ];
        File::put($chemin.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->purger();

        return ['chemin' => $chemin, 'horodatage' => $nom, 'manifest' => $manifest];
    }

    /** @return list<string> */
    public function tables(): array
    {
        $ignorees = (array) config('backup.tables_ignorees', []);

        $toutes = DB::getDriverName() === 'sqlite'
            ? Schema::getTableListing()
            : collect(Schema::getTables())->where('schema', DB::getDatabaseName())->pluck('name')->all();

        return collect($toutes)
            ->map(fn ($t) => str_contains($t, '.') ? explode('.', $t, 2)[1] : $t)
            ->reject(fn ($t) => in_array($t, $ignorees, true))
            ->unique()->sort()->values()->all();
    }

    private function exporterTable(string $table, string $fichier): int
    {
        $flux = fopen($fichier, 'w');
        $n = 0;
        foreach (DB::table($table)->cursor() as $ligne) {
            fwrite($flux, json_encode((array) $ligne, JSON_UNESCAPED_UNICODE)."\n");
            $n++;
        }
        fclose($flux);

        return $n;
    }

    private function copierImports(string $destination): int
    {
        $disque = Storage::disk(config('backup.disque_imports', 'local'));
        $dossier = config('backup.dossier_imports', 'imports');

        if (! $disque->exists($dossier)) {
            return 0;
        }

        File::ensureDirectoryExists($destination);
        $n = 0;
        foreach ($disque->allFiles($dossier) as $chemin) {
            $cible = $destination.'/'.ltrim(substr($chemin, strlen($dossier)), '/');
            File::ensureDirectoryExists(dirname($cible));
            File::put($cible, $disque->get($chemin));
            $n++;
        }

        return $n;
    }

    private function purger(): void
    {
        $conserver = (int) config('backup.conserver', 14);
        if ($conserver <= 0) {
            return;
        }

        $racine = $this->racine ?? config('backup.racine');
        $dossiers = collect(File::directories($racine))->sort()->values();

        $dossiers->slice(0, max(0, $dossiers->count() - $conserver))
            ->each(fn ($d) => File::deleteDirectory($d));
    }
}
