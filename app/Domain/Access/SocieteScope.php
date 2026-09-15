<?php

namespace App\Domain\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Règle 3.1 / exigence 7 (confidentialité par société) — défaut V1 corrigé :
 * le rattachement société d'une personne FILTRE RÉELLEMENT les données visibles.
 *
 *   - administrateur                 => voit toutes les sociétés ;
 *   - personne rattachée à N sociétés => ne voit que les commandes de ces sociétés ;
 *   - personne rattachée à AUCUNE     => ne voit rien (défaut sûr).
 *
 * S'applique via Commande::scopeVisiblePour(). Le taux de satisfaction (2.3)
 * affiche toujours les 3 sociétés côte à côte : c'est un cas explicitement
 * autorisé, géré par le tableau de bord, pas par ce filtre de données brutes.
 */
final class SocieteScope
{
    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     * @return EloquentBuilder|QueryBuilder
     */
    public function apply($query, ?User $user, string $colonne = 'societe_id')
    {
        if ($user !== null && $user->is_admin) {
            return $query;
        }

        $ids = $user?->societeIds() ?? [];

        return $ids === []
            ? $query->whereRaw('1 = 0')          // aucune société => aucune donnée
            : $query->whereIn($colonne, $ids);
    }

    public function seesAll(?User $user): bool
    {
        return $user !== null && $user->is_admin;
    }
}
