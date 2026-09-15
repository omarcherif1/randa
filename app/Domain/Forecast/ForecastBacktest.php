<?php

namespace App\Domain\Forecast;

/**
 * Règle 4.5 — backtest : compare SYSTÉMATIQUEMENT les prévisions passées à la
 * réalité constatée, pour que l'utilisateur juge la fiabilité réelle de la
 * méthode dans le temps (affiché sur la page, pas seulement en interne).
 *
 * Méthode « walk-forward » : pour chaque mois M de l'historique (à partir du 3ᵉ),
 * on recalcule la prévision qui AURAIT été faite avec les seules données
 * antérieures à M, puis on la compare à la valeur réelle de M.
 */
final class ForecastBacktest
{
    /** Nombre minimal de mois avant de commencer à tester (palier 2 mini). */
    private const DEMARRAGE = 3;

    public function __construct(private readonly ForecastEngine $engine = new ForecastEngine) {}

    /**
     * @param  array<string, float>  $serieMensuelle  AAAA-MM => quantité commandée (kg)
     */
    public function evaluer(array $serieMensuelle): array
    {
        ksort($serieMensuelle);
        $mois = array_keys($serieMensuelle);
        $valeurs = array_values($serieMensuelle);
        $k = count($mois);

        $points = [];
        $absErreurs = [];
        $pctErreurs = [];
        $signes = [];

        for ($i = self::DEMARRAGE; $i < $k; $i++) {
            $sousSerie = array_slice($serieMensuelle, 0, $i, true);
            $prev = $this->engine->prevoir($sousSerie);
            if (! $prev->aUnePrevision()) {
                continue;
            }

            $reel = $valeurs[$i];
            $erreur = $prev->quantitePrevueKg - $reel;

            $points[] = [
                'mois' => $mois[$i],
                'prevu_kg' => round($prev->quantitePrevueKg, 1),
                'reel_kg' => round($reel, 1),
                'erreur_kg' => round($erreur, 1),
                'erreur_pct' => $reel > 0 ? round(abs($erreur) / $reel, 4) : null,
            ];
            $absErreurs[] = abs($erreur);
            $signes[] = $erreur;
            if ($reel > 0) {
                $pctErreurs[] = abs($erreur) / $reel;
            }
        }

        $n = count($points);

        return [
            'points' => $points,
            'n_points' => $n,
            'mae_kg' => $n > 0 ? round(array_sum($absErreurs) / $n, 1) : null,
            'mape' => $pctErreurs !== [] ? round(array_sum($pctErreurs) / count($pctErreurs), 4) : null,
            'biais_kg' => $n > 0 ? round(array_sum($signes) / $n, 1) : null, // > 0 : prévision trop haute
        ];
    }
}
