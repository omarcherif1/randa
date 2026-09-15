<?php

namespace App\Console\Commands;

use App\Domain\Backup\DatabaseRestore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Section 3.2 / étape 10 — restaure une sauvegarde produite par `randa:backup`.
 * Opération DESTRUCTIVE : demande confirmation (sauf --force).
 */
class BackupRestoreCommand extends Command
{
    protected $signature = 'randa:restore {chemin? : dossier de sauvegarde (par défaut : le plus récent)} {--force}';

    protected $description = 'Restaure la base et les fichiers importés depuis une sauvegarde';

    public function handle(): int
    {
        $chemin = $this->argument('chemin') ?: $this->plusRecent();

        if ($chemin === null || ! File::isDirectory($chemin)) {
            $this->error('Aucune sauvegarde à restaurer.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Restaurer « {$chemin} » ? Les données actuelles seront écrasées.")) {
            return self::SUCCESS;
        }

        try {
            $manifest = (new DatabaseRestore)->executer($chemin);
        } catch (Throwable $e) {
            $this->error('Restauration échouée : '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info('Restauration terminée (sauvegarde du '.($manifest['cree_le'] ?? '?').').');
        $this->line('  '.array_sum($manifest['tables'] ?? []).' enregistrement(s) rechargé(s).');

        return self::SUCCESS;
    }

    private function plusRecent(): ?string
    {
        $racine = config('backup.racine');

        return File::isDirectory($racine)
            ? collect(File::directories($racine))->sort()->last() ?: null
            : null;
    }
}
