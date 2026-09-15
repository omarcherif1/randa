<?php

namespace App\Domain\Import;

use App\Domain\Quality\ExclusionReason;
use App\Domain\Scope\OrderScope;
use Carbon\CarbonImmutable;

/**
 * Résultat de la normalisation d'une ligne du fichier source par RowNormalizer.
 * Toutes les règles de la section 2 ont déjà été appliquées ici.
 */
final class NormalizedLine
{
    public function __construct(
        public readonly string $numeroCommande,
        public readonly OrderScope $scope,
        public readonly ?string $societeCode,
        public readonly ?CarbonImmutable $dateCommande,
        public readonly ?CarbonImmutable $dateFacture,
        public readonly string $siteVente,
        public readonly string $villeCommande,
        public readonly string $adresseLivraison,
        public readonly string $numeroClient,
        public readonly string $nomClient,
        // Règle 2.7 : catégorie du client (classifié une seule fois, par numéro).
        public readonly ?string $clientCategoryCode,
        public readonly string $clientCategorySource,
        public readonly ?string $clientCategoryDetail,
        public readonly string $article,
        public readonly string $designation,
        public readonly string $uniteVente,
        // Quantités BRUTES (unité d'origine) — base du taux de satisfaction (2.3).
        public readonly float $qteCommandeeRaw,
        public readonly float $qteLivreeRaw,
        public readonly float $qteFactureeRaw,
        // Quantités CONVERTIES en kg (2.8) — base des tonnages. null = poids indéterminé.
        public readonly ?float $qteCommandeeKg,
        public readonly ?float $qteLivreeKg,
        public readonly ?float $qteFactureeKg,
        // Règle 2.8 : poids d'une unité de vente + provenance de ce poids.
        public readonly ?float $poidsUnitaireKg,
        public readonly string $poidsOrigine,
        public readonly bool $volumeFiable,
        public readonly bool $poidsEnConflit,
        public readonly ?string $familleCode,
        public readonly string $familleSource,
        public readonly ?string $familleCodeFichier,
        public readonly ?string $numeroLivraison,
        public readonly ?string $numeroFacture,
        public readonly string $etatLigne,
        public readonly ?ExclusionReason $exclusionReason,
        public readonly bool $isOutlier,
        public readonly ?string $outlierReason,
        public readonly bool $isEligible,
        public readonly bool $isSatisfied,
        public readonly bool $compteVolume = false,
        /** @var list<string> anomalies non bloquantes détectées sur la ligne */
        public readonly array $anomalies = [],
    ) {}

    public function isExcluded(): bool
    {
        return $this->exclusionReason !== null;
    }

    /** Ligne retenue dans les indicateurs (ni exclue, ni aberrante). */
    public function comptabilisee(): bool
    {
        return ! $this->isExcluded() && ! $this->isOutlier;
    }

    public function familleInconnue(): bool
    {
        return $this->familleCode === null;
    }
}
