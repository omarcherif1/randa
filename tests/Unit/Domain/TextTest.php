<?php

namespace Tests\Unit\Domain;

use App\Support\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function test_normalize_majuscule_sans_accent(): void
    {
        $this->assertSame('STE MONOPRIX LAC', Text::normalize('Sté Monoprix  Lac'));
        $this->assertSame('BOULANGERIE DU COIN', Text::normalize('Boulangerie-du-Coin'));
    }

    public function test_to_float_gere_la_virgule_decimale_du_fichier_source(): void
    {
        $this->assertSame(201657.6, Text::toFloat('201657,6'));
        $this->assertSame(1234.56, Text::toFloat('1.234,56'));
        $this->assertSame(1200.0, Text::toFloat('1 200'));
        $this->assertSame(0.0, Text::toFloat(''));
        $this->assertSame(0.0, Text::toFloat('  '));
    }
}
