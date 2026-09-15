<?php

namespace Tests\Unit\Domain;

use App\Domain\Units\DesignationWeightExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.8 — extraction du poids d'une unité de vente depuis la désignation.
 */
class DesignationWeightExtractorTest extends TestCase
{
    private DesignationWeightExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new DesignationWeightExtractor;
    }

    /**
     * @dataProvider designations
     */
    public function test_extrait_le_poids_en_kg(string $designation, ?float $attendu): void
    {
        $this->assertSame($attendu, $this->extractor->extract($designation));
    }

    public static function designations(): array
    {
        return [
            'collé sans espace' => ['Semoule_fine_20KG', 20.0],
            'espace avant KG' => ['Semoule_fine_10 KG', 10.0],
            'petit poids' => ['Semoule_fine_5 KG', 5.0],
            'accent + espace' => ['Farine Spéciale 50 KG', 50.0],
            'casse mixte + suffixe' => ['Semoule SSSE 50 Kg AT', 50.0],
            'unité 1 kg' => ['Farine PS-7 1 KG AT', 1.0],
            'décimale virgule' => ['FELL2_400G_PASTA_R_F:9,6KG', 9.6],
            'grammes ignorés' => ['SP2_500GR_RANDA', null],
            'aucune indication' => ['PRODUIT VRAC SANS INDICATION', null],
            'vide' => ['', null],
            'plusieurs poids contradictoires => ambigu' => ['PACK 20 KG + 5 KG', null],
        ];
    }

    public function test_signale_l_ambiguite(): void
    {
        $this->assertTrue($this->extractor->isAmbiguous('PACK 20 KG + 5 KG'));
        $this->assertFalse($this->extractor->isAmbiguous('Farine Spéciale 50 KG'));
        // deux fois le MÊME poids n'est pas ambigu
        $this->assertFalse($this->extractor->isAmbiguous('50 KG net 50 KG brut'));
    }
}
