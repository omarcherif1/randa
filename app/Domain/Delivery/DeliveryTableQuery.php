<?php

namespace App\Domain\Delivery;

use App\Domain\Import\OrderLifecycle;
use App\Domain\Metrics\DelayCalculator;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Scope\OrderScope;
use App\Models\Commande;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Liste paginée des livraisons (commandes locales) — tri / recherche / filtre
 * d'état CÔTÉ SERVEUR (exigence 7). Le verdict de retard vient de `DelayCalculator`.
 * Résultat mis en cache (mécanisme unique de l'app, invalidé à l'import).
 */
final class DeliveryTableQuery
{
    private const TRIS = ['date_commande', 'numero', 'date_facture', 'etat'];

    public function paginate(Request $request, ?User $user): array
    {
        $tri = in_array($request->input('tri'), self::TRIS, true) ? $request->input('tri') : 'date_commande';
        $sens = $request->input('sens') === 'asc' ? 'asc' : 'desc';
        $recherche = trim((string) $request->input('q', ''));
        $etat = in_array($request->input('etat'), OrderLifecycle::etatsOrdonnes(), true) ? $request->input('etat') : null;
        $page = max(1, (int) $request->input('page', 1));

        $scope = md5(MetricsCache::userScope($user).'|'.$tri.'|'.$sens.'|'.$recherche.'|'.$etat.'|'.$page);

        return MetricsCache::remember('livraisons.table', $scope,
            fn () => $this->build($user, $tri, $sens, $recherche, $etat));
    }

    private function build(?User $user, string $tri, string $sens, string $recherche, ?string $etat): array
    {
        $q = Commande::query()->local()->visiblePour($user)
            ->with('societe:id,code', 'client:id,nom');

        if ($etat) {
            $q->where('etat', $etat);
        }
        if ($recherche !== '') {
            $q->where(fn ($w) => $w->where('numero', 'like', "%{$recherche}%")
                ->orWhere('ville_commande', 'like', "%{$recherche}%")
                ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$recherche}%")));
        }

        $page = $q->orderBy($tri, $sens)->paginate(25)->withQueryString();
        $delay = DelayCalculator::fromSettings();

        return [
            'lignes' => $page->through(function (Commande $c) use ($delay) {
                $v = $delay->evaluate(OrderScope::Local, $c->date_commande, $c->date_facture);

                return [
                    'id' => $c->id,
                    'numero' => $c->numero,
                    'societe' => $c->societe?->code,
                    'client' => $c->client?->nom,
                    'ville_commande' => $c->ville_commande,
                    'date_commande' => $c->date_commande?->toDateString(),
                    'date_facture' => $c->date_facture?->toDateString(),
                    'numero_livraison' => $c->numero_livraison,
                    'etat' => $c->etat,
                    'etat_libelle' => OrderLifecycle::libelle($c->etat),
                    'etat_incertain' => $c->etat_incertain,
                    'retard_statut' => $v->status,
                    'retard_jours' => $v->joursEcoules,
                ];
            })->toArray(),
            'meta' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'dernier' => $page->lastPage(),
                'tri' => $tri, 'sens' => $sens, 'q' => $recherche, 'etat' => $etat,
            ],
        ];
    }
}
