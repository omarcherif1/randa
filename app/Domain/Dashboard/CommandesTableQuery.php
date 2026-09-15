<?php

namespace App\Domain\Dashboard;

use App\Domain\Reporting\MetricsCache;
use App\Models\Commande;
use Illuminate\Http\Request;

/**
 * Table interactive des commandes du tableau de bord : recherche, tri et
 * pagination CÔTÉ SERVEUR (exigence 7 — aucune liste chargée en intégralité).
 *
 * Résultat mis en cache (même mécanisme que tous les agrégats de l'app) :
 * invalidé à l'import ; une nouvelle page / un nouveau tri / un nouveau filtre
 * change la clé, donc seul ce sous-ensemble est recalculé.
 */
final class CommandesTableQuery
{
    private const PAR_PAGE = 25;

    private const TRIS = ['date_commande', 'numero', 'qte_livree_kg', 'non_livre', 'lignes_total', 'etat'];

    public function paginate(Request $request, DashboardFilter $filter): array
    {
        $tri = in_array($request->input('tri'), self::TRIS, true) ? $request->input('tri') : 'date_commande';
        $sens = $request->input('sens') === 'asc' ? 'asc' : 'desc';
        $recherche = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));

        $scope = md5($filter->cacheKey().'|'.$tri.'|'.$sens.'|'.$recherche.'|'.$page);

        return MetricsCache::remember('dashboard.table', $scope,
            fn () => $this->build($filter, $tri, $sens, $recherche));
    }

    private function build(DashboardFilter $filter, string $tri, string $sens, string $recherche): array
    {
        $q = Commande::query()
            ->local()
            ->visiblePour($filter->user)
            ->with('societe:id,code,nom', 'client:id,numero,nom');

        $this->appliquerFiltres($q, $filter);

        if ($recherche !== '') {
            $q->where(function ($w) use ($recherche) {
                $w->where('numero', 'like', "%{$recherche}%")
                    ->orWhere('ville_commande', 'like', "%{$recherche}%")
                    ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$recherche}%")->orWhere('numero', 'like', "%{$recherche}%"));
            });
        }

        if ($tri === 'non_livre') {
            $q->orderByRaw('(qte_commandee_kg - qte_livree_kg) '.$sens);
        } else {
            $q->orderBy($tri, $sens);
        }

        $page = $q->paginate(self::PAR_PAGE)->withQueryString();

        return [
            'lignes' => $page->through(fn (Commande $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'date_commande' => $c->date_commande?->toDateString(),
                'societe' => $c->societe?->code,
                'client' => $c->client?->nom,
                'ville' => $c->ville_commande,
                'etat' => $c->etat,
                'etat_incertain' => $c->etat_incertain,
                'tonnes_livrees' => round(((float) $c->qte_livree_kg) / 1000, 2),
                'tonnes_non_livrees' => round(max(0, (float) $c->qte_commandee_kg - (float) $c->qte_livree_kg) / 1000, 2),
                'satisfaite' => $c->lignes_eligibles > 0 && $c->lignes_satisfaites >= $c->lignes_eligibles,
                'is_excluded' => $c->is_excluded,
                'has_outlier' => $c->has_outlier,
            ])->toArray(),
            'meta' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'dernier' => $page->lastPage(),
                'tri' => $tri,
                'sens' => $sens,
                'q' => $recherche,
            ],
        ];
    }

    private function appliquerFiltres($q, DashboardFilter $filter): void
    {
        if ($filter->societes !== []) {
            $q->whereHas('societe', fn ($s) => $s->whereIn('code', $filter->societes));
        }
        if ($filter->from !== null) {
            $q->whereDate('date_commande', '>=', $filter->from->toDateString());
        }
        if ($filter->to !== null) {
            $q->whereDate('date_commande', '<=', $filter->to->toDateString());
        }
        if ($filter->articles !== []) {
            $q->whereHas('lignes', fn ($l) => $l->whereIn('article', $filter->articles));
        }
        if ($filter->familles !== []) {
            $q->whereHas('lignes.famille', fn ($f) => $f->whereIn('code', $filter->familles));
        }
        if ($filter->clientCategories !== []) {
            $q->whereHas('client.category', fn ($c) => $c->whereIn('code', $filter->clientCategories));
        }
    }
}
