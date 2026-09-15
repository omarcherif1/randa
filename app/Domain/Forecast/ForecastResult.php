<?php

namespace App\Domain\Forecast;

/**
 * Résultat d'une prévision pour un produit (règle 4.5).
 */
final class ForecastResult
{
    public const FIABILITE_INSUFFISANTE = 'insuffisante';

    public const FIABILITE_FAIBLE = 'faible';

    public const FIABILITE_MOYENNE = 'moyenne';

    public const FIABILITE_HAUTE = 'haute';

    public const TENDANCE_HAUSSE = 'hausse';

    public const TENDANCE_BAISSE = 'baisse';

    public const TENDANCE_STABLE = 'stable';

    public const TENDANCE_INDETERMINEE = 'indeterminee';

    public function __construct(
        /** Mois cible (AAAA-MM) de la prévision. */
        public readonly ?string $moisCible,
        /** Quantité COMMANDÉE prévue, en kg (règle 4.5 : jamais la quantité livrée). */
        public readonly ?float $quantitePrevueKg,
        public readonly int $palier,                 // 0 = insuffisant, 1, 2, 3
        public readonly string $fiabilite,
        public readonly string $tendance,
        public readonly int $moisHistorique,
        /** Moyenne mensuelle de l'historique (repère). */
        public readonly ?float $moyenneKg = null,
        /** L'ajustement saisonnier (même mois n-1) a-t-il pu être appliqué ? */
        public readonly bool $ajustementSaisonnier = false,
        public readonly ?string $note = null,
    ) {}

    public function aUnePrevision(): bool
    {
        return $this->quantitePrevueKg !== null;
    }

    public function toArray(): array
    {
        return [
            'mois_cible' => $this->moisCible,
            'quantite_prevue_kg' => $this->quantitePrevueKg,
            'palier' => $this->palier,
            'fiabilite' => $this->fiabilite,
            'tendance' => $this->tendance,
            'mois_historique' => $this->moisHistorique,
            'moyenne_kg' => $this->moyenneKg,
            'ajustement_saisonnier' => $this->ajustementSaisonnier,
            'note' => $this->note,
        ];
    }
}
