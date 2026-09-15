<?php

namespace App\Domain\Classification;

final class FamilyResolution
{
    public const SOURCE_FILE = 'file';

    public const SOURCE_BACKFILLED = 'backfilled';

    public const SOURCE_UNKNOWN = 'unknown';

    public function __construct(
        public readonly ?string $code,
        public readonly string $source,
    ) {}

    public function isKnown(): bool
    {
        return $this->code !== null;
    }
}
