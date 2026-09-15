<?php

namespace App\Domain\Delivery;

use App\Domain\Import\OrderLifecycle;
use App\Domain\Metrics\DelayCalculator;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Scope\OrderScope;
use App\Models\Commande;
use App\Models\User;

/**
 * Page Livraisons (section 4.3) — commandes LOCALES avec leur état réel de cycle
 * de vie (section 1) et la mise en évidence des retards (règle 2.4).
 *
 *   - hérite de l'état déjà calculé à l'import (`commande.etat` / `etat_incertain`) ;
 *   - le verdict de retard passe par `DelayCalculator` (un seul endroit — règle 2.4) :
 *     décision de l'encadreur — la date de FACTURE sert de proxy accepté pour la
 *     date d'expédition ; une commande pas encore facturée reste « donnée
 *     indisponible » pour ELLE, jamais un faux « 0 retard » ;
 *   - « ville » = ville de la COMMANDE, étiquetée comme telle (défaut 4.3 à corriger).
 */
final class DeliveryMetrics
{
    public function forUser(?User $user): array
    {
        return MetricsCache::remember('livraisons', MetricsCache::userScope($user), function () use ($user) {
            $delay = DelayCalculator::fromSettings();

            $parEtat = Commande::query()->local()->visiblePour($user)
                ->selectRaw('etat, COUNT(*) as n')->groupBy('etat')->pluck('n', 'etat');

            $base = Commande::query()->local()->visiblePour($user);

            // Verdict de retard commande par commande (proxy date de facture).
            $retards = ['en_retard' => 0, 'a_temps' => 0, 'donnee_indisponible' => 0];
            (clone $base)->orderBy('date_commande')->chunk(1000, function ($commandes) use ($delay, &$retards) {
                foreach ($commandes as $c) {
                    $v = $delay->evaluate(OrderScope::Local, $c->date_commande, $c->date_facture);
                    $retards[$v->status] = ($retards[$v->status] ?? 0) + 1;
                }
            });

            return [
                'fraicheur' => MetricsCache::fraicheur(),
                'total' => (clone $base)->count(),
                'par_etat' => collect(OrderLifecycle::etatsOrdonnes())
                    ->mapWithKeys(fn ($e) => [$e => (int) ($parEtat[$e] ?? 0)])->all(),
                'incertaines' => (clone $base)->where('etat_incertain', true)->count(),
                'retards' => $retards,
                'retard_exploitable' => true,
                'retard_message' => "Proxy accepté par l'entreprise : écart entre la date de commande et la "
                    ."date de FACTURE (pas de vraie date d'expédition disponible dans la source). "
                    ."Seuil : {$delay->seuilJours()} jour(s). Commandes pas encore facturées : « donnée indisponible », jamais un faux 0.",
                'note_ville' => 'La colonne « Ville » est la ville de la COMMANDE — pas une vraie ville de livraison (cf. 4.3).',
                'note_date' => 'Faute de date d\'expédition, la « date facture » sert de repère : la facturation précède le départ du camion (section 1).',
            ];
        });
    }
}
