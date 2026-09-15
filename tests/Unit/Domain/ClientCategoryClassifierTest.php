<?php

namespace Tests\Unit\Domain;

use App\Domain\Classification\ClientCategoryClassifier;
use App\Domain\Classification\ClientCategoryResolution;
use App\Support\Text;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.7 — classification du type de client par ordre de priorité.
 */
class ClientCategoryClassifierTest extends TestCase
{
    private function classifier(array $referenceMap = []): ClientCategoryClassifier
    {
        $keywords = [
            ['code' => '107', 'keyword' => Text::normalize('MONOPRIX'), 'priorite' => 90],
            ['code' => '107', 'keyword' => Text::normalize('SUPERMARCHE'), 'priorite' => 85],
            ['code' => '108', 'keyword' => Text::normalize('BOULANGERIE'), 'priorite' => 90],
        ];

        return new ClientCategoryClassifier(
            referenceMap: $referenceMap,
            keywords: $keywords,
            exportCategoryCode: '109',
            fallbackCategoryCode: '199',
        );
    }

    public function test_priorite_1_source_fiable_numero_client(): void
    {
        $r = $this->classifier(['1189934M' => '105'])->classify('1189934M', 'STE SOCO CAB');

        $this->assertSame('105', $r->categoryCode);
        $this->assertSame(ClientCategoryResolution::SOURCE_REFERENCE, $r->source);
    }

    public function test_priorite_2_deduction_client_livre_a_l_etranger(): void
    {
        $r = $this->classifier()->classify('X999', 'CLIENT LAMBDA', livreALEtranger: true);

        $this->assertSame('109', $r->categoryCode);
        $this->assertSame(ClientCategoryResolution::SOURCE_DEDUCED, $r->source);
    }

    public function test_priorite_3_reconnaissance_par_mot_cle_dans_le_nom(): void
    {
        $r = $this->classifier()->classify('42K', 'STE MONOPRIX LAC 2');

        $this->assertSame('107', $r->categoryCode);
        $this->assertSame(ClientCategoryResolution::SOURCE_KEYWORD, $r->source);
        $this->assertStringContainsString('MONOPRIX', $r->detail);
    }

    public function test_mot_cle_insensible_aux_accents_et_a_la_casse(): void
    {
        $r = $this->classifier()->classify('7', 'la Boulangerie du coin');

        $this->assertSame('108', $r->categoryCode);
    }

    public function test_priorite_4_indefini_quand_rien_ne_matche(): void
    {
        $r = $this->classifier()->classify('7', 'ETS TOTALEMENT INCONNU');

        $this->assertSame('199', $r->categoryCode);
        $this->assertSame(ClientCategoryResolution::SOURCE_UNDEFINED, $r->source);
        $this->assertFalse($r->isDefined());
    }

    public function test_la_source_fiable_prime_sur_le_mot_cle(): void
    {
        $r = $this->classifier(['9K' => '110'])->classify('9K', 'SUPERMARCHE X');

        $this->assertSame('110', $r->categoryCode);
        $this->assertSame(ClientCategoryResolution::SOURCE_REFERENCE, $r->source);
    }
}
