<?php

namespace App\Console\Commands;

use App\Domain\Backup\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

/**
 * Section 3.2 / étape 10 — sauvegarde de la base + des fichiers sources.
 * Planifiée quotidiennement (routes/console.php) ; relançable à la main.
 */
class BackupRunCommand extends Command
{
    protected $signature = 'randa:backup {--label= : étiquette ajoutée au nom du dossier}';

    protected $description = 'Crée une sauvegarde logique et portable (tables + fichiers importés)';

    public function handle(): int
    {
        try {
            $resultat = (new DatabaseBackup)->executer($this->option('label') ?: null);
        } catch (Throwable $e) {
            $this->error('Sauvegarde échouée : '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $total = array_sum($resultat['manifest']['tables']);
        $this->info("Sauvegarde créée : {$resultat['chemin']}");
        $this->line("  {$total} enregistrement(s) sur ".count($resultat['manifest']['tables']).' table(s)'
            .", {$resultat['manifest']['fichiers_imports']} fichier(s) importé(s).");

        return self::SUCCESS;
    }
}
