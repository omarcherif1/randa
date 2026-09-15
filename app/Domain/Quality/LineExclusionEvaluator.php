<?php

namespace App\Domain\Quality;

/**
 * Règle métier 2.1 — décide si une ligne est une "vraie commande" ou doit être
 * exclue des indicateurs.
 *
 * Défaut V1 corrigé : on ne dépend plus d'un champ "hors périmètre" jamais
 * renseigné. L'exclusion est CALCULÉE à partir de règles objectives :
 *   - quantités (commandée / livrée) ;
 *   - catégorie du client (société du groupe => hors périmètre).
 */
final class LineExclusionEvaluator
{
    /**
     * @param  float  $qteCommandee  quantité commandée (peu importe l'unité, seul le signe/zéro compte ici)
     * @param  float  $qteLivree  quantité livrée
     * @param  bool  $clientEstSocieteGroupe  le client appartient-il au groupe (catégorie "Ste du Groupe") ?
     */
    public function evaluate(float $qteCommandee, float $qteLivree, bool $clientEstSocieteGroupe = false): ?ExclusionReason
    {
        $commande = max(0.0, $qteCommandee);
        $livree = max(0.0, $qteLivree);

        // 1. Ligne totalement vide
        if ($this->isZero($commande) && $this->isZero($livree)) {
            return ExclusionReason::LigneVide;
        }

        // 2. Mouvement interne / don : rien commandé mais quelque chose livré
        if ($this->isZero($commande) && $livree > 0) {
            return ExclusionReason::MouvementInterne;
        }

        // 3. Hors périmètre commercial : société du groupe
        if ($clientEstSocieteGroupe) {
            return ExclusionReason::HorsPerimetre;
        }

        // 4. Commande ouverte sans aucune livraison : pas encore traitée
        if ($commande > 0 && $this->isZero($livree)) {
            return ExclusionReason::OuverteSansLivraison;
        }

        return null;
    }

    private function isZero(float $v): bool
    {
        return abs($v) < 0.0001;
    }
}
