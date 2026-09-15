<?php

namespace App\Console\Commands;

use App\Domain\Import\ImportManager;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Point d'entrée d'import en ligne de commande (même moteur que l'upload web).
 *
 *   php artisan randa:import "F1787140089784_OMSTAGE.csv"
 *   php artisan randa:import stock.csv --type=stock
 */
class ImportCommand extends Command
{
    protected $signature = 'randa:import
        {path : Chemin du fichier à importer}
        {--type=unified : unified | stock}
        {--user= : e-mail de l\'utilisateur à créditer de l\'import}';

    protected $description = 'Importe un fichier (unifié quotidien ou stock) via le moteur d\'import';

    public function handle(ImportManager $manager): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $path = base_path($path);
        }
        if (! is_file($path)) {
            $this->error("Fichier introuvable : {$this->argument('path')}");

            return self::FAILURE;
        }

        $userId = null;
        if ($email = $this->option('user')) {
            $userId = User::where('email', $email)->value('id');
            if ($userId === null) {
                $this->warn("Utilisateur {$email} introuvable — import non crédité.");
            }
        }

        $this->info('Import en cours…');
        $batch = $manager->import($path, basename($path), $this->option('type'), $userId);

        $this->newLine();
        if ($batch->status !== 'completed') {
            $this->error("Import #{$batch->id} : {$batch->status} — {$batch->error_message}");

            return self::FAILURE;
        }

        $this->info("Import #{$batch->id} terminé.");
        $this->table(['Indicateur', 'Valeur'], [
            ['Lignes lues', $batch->rows_total],
            ['Lignes importées', $batch->rows_imported],
            ['Lignes ignorées', $batch->rows_skipped],
            ['Commandes nouvelles', $batch->commandes_new],
            ['Commandes remplacées', $batch->commandes_replaced],
            ['Lignes de commande écrites', $batch->lignes_count],
            ['Clients nouveaux', $batch->clients_new],
            ['Produits nouveaux', $batch->produits_new],
            ['Lignes à famille inconnue', $batch->familles_inconnues_count],
            ['Anomalies à vérifier', $batch->anomalies_count],
            ['Colonne famille retenue', $batch->famille_colonne_index],
        ]);

        foreach ($batch->warnings ?? [] as $w) {
            $this->warn("  {$w}");
        }

        return self::SUCCESS;
    }
}
