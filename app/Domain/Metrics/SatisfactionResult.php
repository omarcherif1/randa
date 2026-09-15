<?php

namespace App\Domain\Metrics;

final class SatisfactionResult
{
    /**
     * @param  array<string, array{eligibles:int, satisfaites:int, taux:?float}>  $parSociete
     */
    public function __construct(
        public readonly array $parSociete,
    ) {}

    public function taux(string $societeCode): ?float
    {
        return $this->parSociete[$societeCode]['taux'] ?? null;
    }

    /**
     * Règle 2.3 : jamais de moyenne entre sociétés. Ce total global n'est fourni
     * QUE comme volume consolidé (lignes), jamais présenté comme "le" taux groupe.
     */
    public function tauxConsolideVolume(): ?float
    {
        $elig = array_sum(array_column($this->parSociete, 'eligibles'));
        $sat = array_sum(array_column($this->parSociete, 'satisfaites'));

        return $elig > 0 ? round($sat / $elig, 4) : null;
    }
}
