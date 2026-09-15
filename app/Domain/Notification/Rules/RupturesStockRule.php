<?php

namespace App\Domain\Notification\Rules;

use App\Domain\Notification\Alert;
use App\Domain\Notification\AlertRule;
use App\Domain\Stock\StockMetrics;
use App\Models\User;

/**
 * Section 6 — jusqu'à 5 produits les plus critiques en rupture, couverture la
 * plus faible en premier.
 *
 * Consomme l'agrégat déjà calculé par {@see StockMetrics} (règle 2.5, méthode
 * active A ou B — un seul endroit) : cette règle ne recalcule aucune couverture.
 */
final class RupturesStockRule implements AlertRule
{
    public function __construct(private readonly StockMetrics $stock = new StockMetrics) {}

    public function cle(): string
    {
        return 'ruptures_stock';
    }

    public function pageRequise(): ?string
    {
        return 'stock';
    }

    public function evaluer(?User $user): array
    {
        $etat = $this->stock->forUser($user);
        $methode = $etat['methode'];

        $ruptures = array_values(array_filter(
            $etat['produits'],
            fn ($p) => $p['statut'] === 'rupture',
        ));

        // Couverture la plus faible d'abord (jours en méthode A, taux en méthode B).
        usort($ruptures, fn ($a, $b) => $this->niveau($a) <=> $this->niveau($b));
        $max = (int) config('notifications.max_par_regle', 5);

        return array_map(function ($p) use ($methode) {
            $mesure = $methode === 'A'
                ? round((float) ($p['jours_couverture'] ?? 0), 1).' jour(s) de couverture'
                : round((float) ($p['taux_couverture'] ?? 0) * 100).' % de couverture';

            return new Alert(
                type: 'rupture_stock',
                severity: Alert::CRITIQUE,
                titre: 'Rupture : '.$p['designation'],
                detail: "Article {$p['article']} — {$mesure} (méthode {$methode}).",
                routeName: 'stock',
                reference: 'rupture:'.$p['article'],
            );
        }, array_slice($ruptures, 0, $max));
    }

    /** @param array<string, mixed> $p */
    private function niveau(array $p): float
    {
        return (float) ($p['jours_couverture'] ?? $p['taux_couverture'] ?? 0);
    }
}
