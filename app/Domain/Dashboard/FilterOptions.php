<?php

namespace App\Domain\Dashboard;

use App\Domain\Access\SocieteScope;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Scope\OrderScope;
use App\Models\ClientCategory;
use App\Models\Famille;
use App\Models\Societe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Valeurs disponibles dans les listes déroulantes du tableau de bord (règle 2.10).
 * Règle 2.2 : AUCUNE trace des données export — ni clients, ni produits, ni villes.
 */
final class FilterOptions
{
    public function forUser(?User $user): array
    {
        $societes = Societe::query();
        if ($user !== null && ! $user->is_admin) {
            $societes->whereIn('id', $user->societeIds() ?: [-1]);
        }

        return MetricsCache::remember('dashboard.filters', MetricsCache::userScope($user), fn () => [
            'societes' => $societes->orderBy('nom')->get(['code', 'nom', 'couleur']),
            'client_categories' => $this->clientCategoriesUtilisees($user),
            'familles' => $this->famillesUtilisees($user),
            'articles' => $this->articlesUtilises($user),
        ]);
    }

    /**
     * Uniquement les catégories client réellement utilisées par au moins une
     * commande locale visible par cet utilisateur (même principe que
     * `famillesUtilisees()`/`articlesUtilises()`) — le référentiel
     * `client_categories` contient 21 catégories partagées par tout le
     * groupe, dont la moitié n'a aucun client dans ce périmètre (ex.
     * Éleveurs, Hôtel, Restaurant…) : les proposer dans le filtre menait à
     * une sélection qui ne pouvait renvoyer AUCUNE donnée, à tort perçue
     * comme un filtre cassé.
     *
     * @return array<int, array{code:string, intitule:string}>
     */
    private function clientCategoriesUtilisees(?User $user): array
    {
        $ids = $this->baseLocale($user)
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->whereNotNull('cl.client_category_id')
            ->distinct()->pluck('cl.client_category_id');

        return ClientCategory::whereIn('id', $ids)->orderBy('intitule')->get(['code', 'intitule'])->all();
    }

    /** @return array<int, array{code:string, intitule:string}> */
    private function famillesUtilisees(?User $user): array
    {
        $ids = $this->baseLocale($user)
            ->whereNotNull('l.famille_id')
            ->distinct()->pluck('l.famille_id');

        return Famille::whereIn('id', $ids)->orderBy('intitule')->get(['code', 'intitule'])->all();
    }

    /** @return array<int, array{article:string, designation:?string}> */
    private function articlesUtilises(?User $user): array
    {
        return $this->baseLocale($user)
            ->select('l.article', DB::raw('MAX(l.designation) as designation'))
            ->whereNotNull('l.article')
            ->groupBy('l.article')
            ->orderBy('l.article')
            ->get()
            ->map(fn ($r) => ['article' => $r->article, 'designation' => $r->designation])
            ->all();
    }

    private function baseLocale(?User $user)
    {
        $q = DB::table('commande_lignes as l')
            ->join('commandes as c', 'c.id', '=', 'l.commande_id')
            ->where('c.scope', OrderScope::Local->value);

        (new SocieteScope)->apply($q, $user, 'c.societe_id');

        return $q;
    }
}
