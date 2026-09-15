<?php

namespace Tests\Unit\Domain;

use App\Domain\Classification\FamilyColumnDetector;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.6 — détection automatique de la bonne colonne "Famille statistique"
 * (position variable, jamais codée en dur).
 */
class FamilyColumnDetectorTest extends TestCase
{
    private array $codesReference = ['100', '106', '107', '113', '121', '130'];

    public function test_choisit_la_colonne_qui_contient_de_vrais_codes_famille(): void
    {
        $header = ['Article', 'Famille statistique', 'Famille statistique', 'Etat ligne'];
        $rows = [
            ['SP2', 'STAT1', '106', 'Soldé'],
            ['LAS', 'STAT1', '107', 'Soldé'],
            ['COU', 'STAT1', '100', 'Soldé'],
            ['HLE', 'STAT1', '121', 'Soldé'],
            ['NOU', 'STAT1', '106', 'Soldé'],
        ];

        $result = (new FamilyColumnDetector)->detect($header, $rows, $this->codesReference);

        $this->assertSame(2, $result->chosenIndex);
        $this->assertTrue($result->isReliable());
    }

    public function test_prend_la_colonne_valide_la_plus_a_gauche_en_cas_d_ambiguite(): void
    {
        $header = ['Article', 'Famille statistique', 'Famille statistique'];
        $rows = [
            ['A', '106', '113'],
            ['B', '107', '121'],
            ['C', '100', '130'],
            ['D', '106', '113'],
            ['E', '107', '121'],
        ];

        $result = (new FamilyColumnDetector)->detect($header, $rows, $this->codesReference);

        $this->assertSame(1, $result->chosenIndex);
        $this->assertTrue($result->hasAmbiguity());
    }

    public function test_signale_l_absence_de_colonne_fiable(): void
    {
        $header = ['Article', 'Famille statistique'];
        $rows = [
            ['A', 'ZZZ'],
            ['B', ''],
            ['C', 'STAT1'],
        ];

        $result = (new FamilyColumnDetector)->detect($header, $rows, $this->codesReference);

        $this->assertNull($result->chosenIndex);
        $this->assertFalse($result->isReliable());
    }
}
