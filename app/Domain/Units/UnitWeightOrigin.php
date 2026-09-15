<?php

namespace App\Domain\Units;

/**
 * Règle 2.8 — origine du poids unitaire retenu pour convertir une quantité en kg.
 * Sert de "preuve de fiabilité" sur la page d'administration : rien n'est deviné
 * silencieusement, chaque conversion sait d'où vient son facteur.
 */
final class UnitWeightOrigin
{
    /** Unité déjà en kg : aucune conversion nécessaire (cas volontairement neutre). */
    public const KG_NEUTRE = 'kg_neutre';

    /** Poids extrait automatiquement du texte de la désignation (ex. "…20 KG…"). */
    public const EXTRAIT_DESIGNATION = 'extrait_designation';

    /** Quintal : 1 QX = 100 kg par défaut, hypothèse NON confirmée par l'entreprise. */
    public const HYPOTHESE_QUINTAL = 'hypothese_quintal';

    /** Aucun poids trouvable : la ligne est exclue des tonnages (jamais de facteur inventé). */
    public const NON_DETERMINE = 'non_determine';

    /** Valeur saisie/validée manuellement par un administrateur. */
    public const CORRECTION_MANUELLE = 'correction_manuelle';

    public static function libelle(string $origine): string
    {
        return match ($origine) {
            self::KG_NEUTRE => 'Aucune conversion nécessaire (KG)',
            self::EXTRAIT_DESIGNATION => 'Extrait automatiquement de la désignation',
            self::HYPOTHESE_QUINTAL => 'Hypothèse quintal (100 kg) — non confirmée',
            self::NON_DETERMINE => 'Non déterminé — lignes exclues des tonnages',
            self::CORRECTION_MANUELLE => 'Corrigé manuellement',
            default => $origine,
        };
    }

    /** Le poids est-il tenu pour fiable (vs simple hypothèse / indéterminé) ? */
    public static function estFiable(string $origine): bool
    {
        return in_array($origine, [
            self::KG_NEUTRE,
            self::EXTRAIT_DESIGNATION,
            self::CORRECTION_MANUELLE,
        ], true);
    }
}
