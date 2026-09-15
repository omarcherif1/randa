<?php

namespace App\Domain\Reporting;

use Illuminate\Support\Facades\DB;

/**
 * Petits fragments SQL portables (MySQL en production, SQLite pour les tests).
 */
final class Sql
{
    /** Expression « année-mois » (AAAA-MM) à partir d'une colonne date. */
    public static function monthExpr(string $colonne): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$colonne})"
            : "DATE_FORMAT({$colonne}, '%Y-%m')";
    }
}
