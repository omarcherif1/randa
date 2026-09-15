<?php

namespace App\Domain\Classification;

final class FamilyColumnResult
{
    /**
     * @param  int|null  $chosenIndex  index de colonne retenu (null si aucune colonne fiable)
     * @param  array<int, array<string, mixed>>  $candidates  détail de toutes les colonnes candidates
     * @param  int  $sampleSize  nombre de lignes analysées
     */
    public function __construct(
        public readonly ?int $chosenIndex,
        public readonly array $candidates,
        public readonly int $sampleSize,
    ) {}

    public function isReliable(): bool
    {
        return $this->chosenIndex !== null;
    }

    public function hasAmbiguity(): bool
    {
        $retenables = array_filter($this->candidates, fn ($c) => $c['retenable']);

        return count($retenables) > 1;
    }
}
