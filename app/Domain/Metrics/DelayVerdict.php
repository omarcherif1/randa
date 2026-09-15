<?php

namespace App\Domain\Metrics;

final class DelayVerdict
{
    public const A_TEMPS = 'a_temps';

    public const EN_RETARD = 'en_retard';

    public const DONNEE_INDISPONIBLE = 'donnee_indisponible';

    public const NON_APPLICABLE = 'non_applicable'; // export

    public function __construct(
        public readonly string $status,
        public readonly ?int $joursEcoules = null,
        public readonly ?int $seuilJours = null,
        public readonly ?string $raison = null,
    ) {}

    public function isDataAvailable(): bool
    {
        return ! in_array($this->status, [self::DONNEE_INDISPONIBLE, self::NON_APPLICABLE], true);
    }

    public function isLate(): bool
    {
        return $this->status === self::EN_RETARD;
    }
}
