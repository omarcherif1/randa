<?php

namespace App\Domain\Classification;

/**
 * Règle 2.6 (complément) — décision consolidée pour UN article après un import :
 * quelle famille retenir compte tenu de toutes les lignes observées.
 */
final class FamilyConsolidation
{
    /** Une seule famille jamais vue pour cet article. */
    public const STATUT_CONFIRME = 'confirme';

    /** Familles disjointes dans le temps : reclassification légitime, on garde la plus récente. */
    public const STATUT_RECLASSIFICATION = 'reclassification';

    /** Familles incohérentes sans logique de date : aucun choix automatique. */
    public const STATUT_AMBIGU = 'ambigu';

    public function __construct(
        public readonly string $article,
        /** Famille retenue ; null si ambigu et aucune valeur confirmée antérieure. */
        public readonly ?string $familleCode,
        public readonly string $statut,
        /** @var list<array{code:string, from:?string, to:?string, count:int}> */
        public readonly array $historique,
    ) {}

    public function estAmbigu(): bool
    {
        return $this->statut === self::STATUT_AMBIGU;
    }

    public function estReclassification(): bool
    {
        return $this->statut === self::STATUT_RECLASSIFICATION;
    }

    /** A-t-elle changé par rapport à la valeur déjà mémorisée ? */
    public function aChange(?string $codePrecedent): bool
    {
        return $this->familleCode !== null && $this->familleCode !== $codePrecedent;
    }
}
