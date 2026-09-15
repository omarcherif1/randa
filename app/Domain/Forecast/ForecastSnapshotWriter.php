<?php

namespace App\Domain\Forecast;

use App\Models\ForecastSnapshot;
use App\Models\ImportBatch;
use App\Models\Societe;
use Illuminate\Support\Facades\DB;

/**
 * Règle 4.5 — enregistre la photo des prévisions courantes, par société et par
 * produit, à chaque import. Permet un vrai backtest « ce qu'on avait prévu vs
 * ce qui s'est passé » qui s'enrichit dans le temps.
 *
 * Le calcul lui-même reste dans `ForecastEngine` (un seul endroit).
 */
final class ForecastSnapshotWriter
{
    public function __construct(private readonly ForecastEngine $engine = new ForecastEngine) {}

    public function run(?ImportBatch $batch = null): int
    {
        $now = now();
        $lignes = [];

        foreach (Societe::all() as $societe) {
            $metrics = new ForecastMetrics($this->engine);
            $produits = $metrics->previsionsCalculSeules(new ForecastFilter(societeCode: $societe->code));

            foreach ($produits as $article => $p) {
                $prev = $p['prevision'];
                if ($prev->moisCible === null) {
                    continue;
                }
                $lignes[] = [
                    'article' => $article,
                    'designation' => $p['designation'],
                    'societe_id' => $societe->id,
                    'mois_cible' => $prev->moisCible,
                    'qte_commandee_prevue_kg' => $prev->quantitePrevueKg,
                    'palier' => $prev->palier,
                    'fiabilite' => $prev->fiabilite,
                    'tendance' => $prev->tendance,
                    'mois_historique' => $prev->moisHistorique,
                    'ajustement_saisonnier' => $prev->ajustementSaisonnier,
                    'import_batch_id' => $batch?->id,
                    'genere_le' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($lignes) {
            foreach (array_chunk($lignes, 500) as $chunk) {
                ForecastSnapshot::upsert(
                    $chunk,
                    ['article', 'societe_id', 'mois_cible'],
                    ['designation', 'qte_commandee_prevue_kg', 'palier', 'fiabilite', 'tendance',
                        'mois_historique', 'ajustement_saisonnier', 'import_batch_id', 'genere_le', 'updated_at'],
                );
            }
        });

        return count($lignes);
    }
}
