<?php

namespace App\Domain\Export;

use App\Domain\Import\OrderLifecycle;
use App\Domain\Reporting\MetricsCache;
use App\Models\Commande;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Liste paginée des commandes EXPORT (règle 2.2 — jamais mélangée au local).
 * Tri / recherche / pagination côté serveur (exigence 7).
 * Résultat mis en cache (mécanisme unique de l'app, invalidé à l'import).
 */
final class ExportTableQuery
{
    private const TRIS = ['date_commande', 'numero', 'qte_livree_kg', 'etat'];

    public function paginate(Request $request, ?User $user): array
    {
        $tri = in_array($request->input('tri'), self::TRIS, true) ? $request->input('tri') : 'date_commande';
        $sens = $request->input('sens') === 'asc' ? 'asc' : 'desc';
        $recherche = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));

        $scope = md5(MetricsCache::userScope($user).'|'.$tri.'|'.$sens.'|'.$recherche.'|'.$page);

        return MetricsCache::remember('export.table', $scope,
            fn () => $this->build($user, $tri, $sens, $recherche));
    }

    private function build(?User $user, string $tri, string $sens, string $recherche): array
    {
        $q = Commande::query()->export()->visiblePour($user)
            ->with('societe:id,code', 'client:id,nom');

        if ($recherche !== '') {
            $q->where(fn ($w) => $w->where('numero', 'like', "%{$recherche}%")
                ->orWhere('ville_commande', 'like', "%{$recherche}%")
                ->orWhereHas('client', fn ($c) => $c->where('nom', 'like', "%{$recherche}%")));
        }

        $page = $q->orderBy($tri, $sens)->paginate(25)->withQueryString();

        return [
            'lignes' => $page->through(fn (Commande $c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'societe' => $c->societe?->code,
                'client' => $c->client?->nom,
                'destination' => $c->ville_commande,
                'date_commande' => $c->date_commande?->toDateString(),
                'etat' => OrderLifecycle::libelle($c->etat),
                'tonnes_livrees' => round(((float) $c->qte_livree_kg) / 1000, 2),
                'satisfaite' => $c->lignes_eligibles > 0 && $c->lignes_satisfaites >= $c->lignes_eligibles,
            ])->toArray(),
            'meta' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'dernier' => $page->lastPage(),
                'tri' => $tri, 'sens' => $sens, 'q' => $recherche,
            ],
        ];
    }
}
