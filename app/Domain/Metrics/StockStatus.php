<?php

namespace App\Domain\Metrics;

enum StockStatus: string
{
    case Rupture = 'rupture';
    case Alerte = 'alerte';
    case Normal = 'normal';
    case Indetermine = 'indetermine';

    public function label(): string
    {
        return match ($this) {
            self::Rupture => 'Rupture',
            self::Alerte => 'Alerte',
            self::Normal => 'Normal',
            self::Indetermine => 'Indéterminé',
        };
    }
}
