<?php

namespace App\Domain\Quality;

final class OutlierVerdict
{
    public function __construct(
        public readonly bool $isOutlier,
        public readonly ?string $reason = null,
        public readonly array $context = [],
    ) {}

    public static function ok(): self
    {
        return new self(false);
    }

    public static function flag(string $reason, array $context = []): self
    {
        return new self(true, $reason, $context);
    }
}
