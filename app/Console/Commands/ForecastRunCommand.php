<?php

namespace App\Console\Commands;

use App\Domain\Forecast\ForecastSnapshotWriter;
use Illuminate\Console\Command;

/**
 * Recalcule et enregistre la photo des prévisions (par société, par produit).
 * Lancé automatiquement après chaque import ; peut être relancé à la main.
 */
class ForecastRunCommand extends Command
{
    protected $signature = 'randa:forecast:run';

    protected $description = 'Recalcule et enregistre les prévisions de demande (snapshots)';

    public function handle(): int
    {
        $n = (new ForecastSnapshotWriter)->run();
        $this->info("{$n} prévision(s) enregistrée(s).");

        return self::SUCCESS;
    }
}
