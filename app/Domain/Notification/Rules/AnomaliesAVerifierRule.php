<?php

namespace App\Domain\Notification\Rules;

use App\Domain\Notification\Alert;
use App\Domain\Notification\AlertRule;
use App\Models\Anomalie;
use App\Models\User;

/**
 * Section 6 (extension) — des anomalies détectées à l'import attendent l'arbitrage
 * d'un administrateur (valeur aberrante, famille ambiguë, poids indéterminé, …).
 *
 * Exemple d'extensibilité (« nouvelle anomalie détectée »). Réservée aux personnes
 * ayant accès à l'Administration ; le décompte est invalidé dès qu'une anomalie
 * est tranchée (l'arbitrage appelle `MetricsCache::bump()`).
 */
final class AnomaliesAVerifierRule implements AlertRule
{
    public function cle(): string
    {
        return 'anomalies_a_verifier';
    }

    public function pageRequise(): ?string
    {
        return 'administration';
    }

    public function evaluer(?User $user): array
    {
        $n = Anomalie::where('statut', Anomalie::STATUT_A_VERIFIER)->count();

        if ($n === 0) {
            return [];
        }

        return [new Alert(
            type: 'anomalies_a_verifier',
            severity: $n >= 20 ? Alert::ALERTE : Alert::INFO,
            titre: $n.' anomalie(s) à vérifier',
            detail: 'Des lignes sont hors des indicateurs en attendant votre arbitrage (valider / exclure).',
            routeName: 'admin.anomalies',
            reference: 'anomalies_a_verifier',
        )];
    }
}
