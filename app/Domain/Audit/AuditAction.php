<?php

namespace App\Domain\Audit;

/**
 * Vocabulaire des actions sensibles journalisées (règle 3.2).
 */
final class AuditAction
{
    public const COMPTE_CREE = 'compte.cree';

    public const COMPTE_VERROUILLE = 'compte.verrouille';

    public const COMPTE_DEVERROUILLE = 'compte.deverrouille';

    public const MOT_DE_PASSE_REINITIALISE = 'compte.mot_de_passe_reinitialise';

    public const DROITS_MODIFIES = 'droits.modifies';

    public const SOCIETES_MODIFIEES = 'droits.societes_modifiees';

    public const MODELE_APPLIQUE = 'droits.modele_applique';

    public const PARAMETRE_MODIFIE = 'parametre.modifie';

    public const MOTS_CLES_MODIFIES = 'classification.mots_cles_modifies';

    public const CLIENTS_RECLASSES = 'classification.clients_reclasses';

    public const ANOMALIE_TRANCHEE = 'anomalie.tranchee';

    public const DONNEES_SUPPRIMEES = 'donnees.supprimees';

    public const IMPORT_LANCE = 'import.lance';
}
