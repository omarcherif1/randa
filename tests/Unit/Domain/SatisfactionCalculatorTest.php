<?php

namespace Tests\Unit\Domain;

use App\Domain\Metrics\SatisfactionCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.3 — taux de satisfaction, par société, jamais moyenné.
 */
class SatisfactionCalculatorTest extends TestCase
{
    private SatisfactionCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new SatisfactionCalculator;
    }

    public function test_ligne_satisfaite_si_livree_superieure_ou_egale_a_commandee(): void
    {
        $this->assertTrue($this->calc->isLineSatisfied(1200, 1200));
        $this->assertTrue($this->calc->isLineSatisfied(1000, 1200));
        $this->assertFalse($this->calc->isLineSatisfied(120, 60));
    }

    public function test_taux_calcule_par_societe_sans_moyenne(): void
    {
        $lignes = [
            ['societe_code' => 'RAN', 'eligible' => true, 'satisfaite' => true],
            ['societe_code' => 'RAN', 'eligible' => true, 'satisfaite' => true],
            ['societe_code' => 'RAN', 'eligible' => true, 'satisfaite' => false],
            ['societe_code' => 'RAN', 'eligible' => true, 'satisfaite' => true],
            ['societe_code' => 'SMT', 'eligible' => true, 'satisfaite' => false],
            ['societe_code' => 'SMT', 'eligible' => true, 'satisfaite' => false],
        ];

        $result = $this->calc->compute($lignes);

        $this->assertSame(0.75, $result->taux('RAN'));
        $this->assertSame(0.0, $result->taux('SMT'));
        // le taux de RAN ne "remonte" pas celui de SMT et inversement
        $this->assertNotEquals($result->taux('RAN'), $result->taux('SMT'));
    }

    public function test_les_lignes_non_eligibles_sont_ignorees(): void
    {
        $lignes = [
            ['societe_code' => 'RAN', 'eligible' => true, 'satisfaite' => true],
            ['societe_code' => 'RAN', 'eligible' => false, 'satisfaite' => false], // exclue (règle 2.1) / aberrante (2.9)
        ];

        $result = $this->calc->compute($lignes);

        $this->assertSame(1.0, $result->taux('RAN'));
        $this->assertSame(1, $result->parSociete['RAN']['eligibles']);
    }

    public function test_taux_null_si_aucune_ligne_eligible(): void
    {
        $result = $this->calc->compute([
            ['societe_code' => 'TF', 'eligible' => false, 'satisfaite' => false],
        ]);

        $this->assertNull($result->taux('TF'));
    }
}
