<?php

namespace App\Domain\Quality;

/**
 * Règle métier 2.1 — motifs d'exclusion d'une ligne de TOUS les indicateurs
 * (la ligne n'est jamais supprimée de la base).
 */
enum ExclusionReason: string
{
    /** Quantité commandée = 0 ET quantité livrée > 0 (transfert interne, don). */
    case MouvementInterne = 'mouvement_interne';

    /** Client identifié comme société du groupe / pas de vrai bon de commande. */
    case HorsPerimetre = 'hors_perimetre';

    /** Commandé = 0 ET livré = 0. */
    case LigneVide = 'ligne_vide';

    /** Reste à livrer > 0 ET rien livré : commande pas encore traitée. */
    case OuverteSansLivraison = 'ouverte_sans_livraison';

    public function label(): string
    {
        return match ($this) {
            self::MouvementInterne => 'Mouvement interne / don',
            self::HorsPerimetre => 'Hors périmètre commercial',
            self::LigneVide => 'Ligne vide',
            self::OuverteSansLivraison => 'Commande ouverte sans livraison',
        };
    }

    /**
     * Ces lignes sortent de TOUS les indicateurs.
     * (Toutes le font aujourd'hui, mais le distinguo est explicite pour l'avenir.)
     */
    public function excludesFromAllMetrics(): bool
    {
        return true;
    }
}
