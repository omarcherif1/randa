<?php

namespace App\Domain\Metrics;

final class StockCoverageResult
{
    public function __construct(
        public readonly StockStatus $status,
        public readonly string $methode,               // 'A' | 'B'
        public readonly ?float $joursCouverture = null, // méthode A
        public readonly ?float $tauxCouverture = null,  // méthode B
        public readonly ?string $detail = null,
    ) {}
}
