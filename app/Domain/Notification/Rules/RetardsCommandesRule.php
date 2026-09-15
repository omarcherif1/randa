<?php

namespace App\Domain\Notification\Rules;

use App\Domain\Metrics\DelayCalculator;
use App\Domain\Notification\Alert;
use App\Domain\Notification\AlertRule;
use App\Domain\Scope\OrderScope;
use App\Models\Commande;
use App\Models\User;

/**
 * Section 6 — jusqu'à 5 commandes LOCALES les plus en retard.
 *
 * Le verdict de retard passe uniquement par {@see DelayCalculator} (règle 2.4,
 * un seul endroit) : proxy accepté par l'entreprise sur la date de FACTURE.
 * Une commande pas encore facturée répond « donnée indisponible » : elle est
 * silencieusement ignorée ici — jamais un faux retard (principe directeur n°1).
 */
final class RetardsCommandesRule implements AlertRule
{
    public function cle(): string
    {
        return 'retards_commandes';
    }

    public function pageRequise(): ?string
    {
        return 'livraisons';
    }

    public function evaluer(?User $user): array
    {
        $delay = DelayCalculator::fromSettings();

        $enRetard = [];
        Commande::query()->local()->retenues()->visiblePour($user)
            ->with('client:id,nom')
            ->chunkById(1000, function ($commandes) use ($delay, &$enRetard) {
                foreach ($commandes as $commande) {
                    $verdict = $delay->evaluate(OrderScope::Local, $commande->date_commande, $commande->date_facture);
                    if ($verdict->isLate()) {
                        $enRetard[] = [$commande, $verdict->joursEcoules, $verdict->seuilJours];
                    }
                }
            });

        usort($enRetard, fn ($a, $b) => $b[1] <=> $a[1]);
        $max = (int) config('notifications.max_par_regle', 5);

        return array_map(function ($item) {
            [$commande, $jours, $seuil] = $item;
            $client = $commande->client?->nom ?? 'client inconnu';

            return new Alert(
                type: 'retard_commande',
                severity: Alert::CRITIQUE,
                titre: "Commande {$commande->numero} en retard de ".($jours - $seuil).' j',
                detail: "{$client} — {$commande->ville_commande} · {$jours} jours entre commande et facture (seuil {$seuil} j).",
                routeName: 'livraisons',
                reference: 'retard:'.$commande->numero,
            );
        }, array_slice($enRetard, 0, $max));
    }
}
