<?php

namespace App\Domain\Units;

/**
 * Résultat de la résolution du poids unitaire d'un article (règle 2.8).
 *
 * IMPORTANT : ce poids sert UNIQUEMENT à convertir vers les kg (tonnages).
 * Le taux de satisfaction, lui, compare commandé/livré dans l'unité d'origine
 * et n'utilise jamais ce poids.
 */
final class UnitWeightResolution
{
    public function __construct(
        /** Poids en kg d'une unité de vente ; null = non déterminé. */
        public readonly ?float $poidsUnitaireKg,
        /** Une des constantes UnitWeightOrigin. */
        public readonly string $origine,
        /** Contradiction détectée avec une valeur déjà validée (on garde l'ancienne). */
        public readonly bool $enConflit = false,
        /** @var array<string,mixed>|null détail du conflit pour l'administrateur */
        public readonly ?array $conflitDetails = null,
    ) {}

    /** La ligne peut-elle entrer dans un total en kg / tonnes ? */
    public function compteVolume(): bool
    {
        return $this->poidsUnitaireKg !== null;
    }

    /** Le tonnage produit est-il fiable, ou seulement une hypothèse (QX) ? */
    public function volumeFiable(): bool
    {
        return $this->compteVolume() && UnitWeightOrigin::estFiable($this->origine);
    }

    public function convertir(float $quantite): ?float
    {
        if ($this->poidsUnitaireKg === null) {
            return null;
        }

        return round($quantite * $this->poidsUnitaireKg, 3);
    }
}
