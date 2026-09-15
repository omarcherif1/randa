<?php

namespace App\Domain\Scope;

/**
 * Règle métier 2.2 — séparation stricte Export / Local.
 */
enum OrderScope: string
{
    case Local = 'local';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Local',
            self::Export => 'Export',
        };
    }
}
