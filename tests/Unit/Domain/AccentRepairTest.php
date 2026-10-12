<?php

namespace Tests\Unit\Domain;

use App\Domain\Import\AccentRepair;
use PHPUnit\Framework\TestCase;

/**
 * Extraction F1790079066766 : accents remplacés par « � » — réparés d'après le
 * lexique, seulement quand un candidat unique existe.
 */
class AccentRepairTest extends TestCase
{
    private const F = "\u{FFFD}";

    private function repair(): AccentRepair
    {
        return new AccentRepair(['Déchet', 'dérivés', 'Soldé', 'Spéciale', 'étuvé', 'Mélé', 'Mêlé']);
    }

    public function test_repare_d_apres_le_lexique_en_gardant_la_casse(): void
    {
        $r = $this->repair();
        $f = self::F;

        $this->assertSame('Déchet des dérivés industriels', $r->repair("D{$f}chet des d{$f}riv{$f}s industriels"));
        $this->assertSame('Soldé', $r->repair("Sold{$f}"));
        $this->assertSame('FARINE SPÉCIALE 50 KG', $r->repair("FARINE SP{$f}CIALE 50 KG"));
        $this->assertSame('Riz étuvé 1kg', $r->repair("Riz {$f}tuv{$f} 1kg"));
        $this->assertSame([], $r->nonRepares());
    }

    public function test_sans_candidat_unique_retire_le_caractere_et_signale(): void
    {
        $r = $this->repair();
        $f = self::F;

        $this->assertSame('ALIMENTAT GLE', $r->repair("ALIMENTAT{$f} GLE"));
        $this->assertSame('Ml', $r->repair("M{$f}l{$f}"), 'Mélé / Mêlé : ambigu, jamais deviné');
        $r->repair("ALIMENTAT{$f}");

        $this->assertSame(["ALIMENTAT{$f}" => 2, "M{$f}l{$f}" => 1], $r->nonRepares());
    }

    public function test_texte_sans_remplacement_inchange(): void
    {
        $this->assertSame('Farine PS-7 20 KG', $this->repair()->repair('Farine PS-7 20 KG'));
    }
}
