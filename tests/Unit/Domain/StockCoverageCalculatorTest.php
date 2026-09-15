<?php

namespace Tests\Unit\Domain;

use App\Domain\Metrics\StockCoverageCalculator;
use App\Domain\Metrics\StockStatus;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.5 — ruptures de stock, méthodes A (stock journalier) et B (déduction).
 */
class StockCoverageCalculatorTest extends TestCase
{
    private function calc(): StockCoverageCalculator
    {
        return new StockCoverageCalculator(
            seuilRupturePct: 0.5,
            couvertureJoursRupture: 2,
            couvertureJoursAlerte: 7,
        );
    }

    public function test_methode_a_rupture_alerte_normal(): void
    {
        $c = $this->calc();

        $this->assertSame(StockStatus::Rupture, $c->fromDailyStock(1000, 800)->status);   // 1,25 j
        $this->assertSame(StockStatus::Alerte, $c->fromDailyStock(4000, 800)->status);    // 5 j
        $this->assertSame(StockStatus::Normal, $c->fromDailyStock(20000, 800)->status);   // 25 j
    }

    public function test_methode_a_indetermine_si_rythme_de_vente_nul(): void
    {
        $this->assertSame(StockStatus::Indetermine, $this->calc()->fromDailyStock(1000, 0)->status);
    }

    public function test_methode_b_sur_les_commandes_ouvertes(): void
    {
        $c = $this->calc();

        $this->assertSame(StockStatus::Rupture, $c->fromOpenOrders(1000, 300)->status);   // 30 %
        $this->assertSame(StockStatus::Alerte, $c->fromOpenOrders(1000, 700)->status);    // 70 %
        $this->assertSame(StockStatus::Normal, $c->fromOpenOrders(1000, 1000)->status);   // 100 %
    }

    public function test_methode_b_expose_le_taux_et_la_methode(): void
    {
        $r = $this->calc()->fromOpenOrders(1000, 250);

        $this->assertSame('B', $r->methode);
        $this->assertSame(0.25, $r->tauxCouverture);
    }

    public function test_seuil_rupture_reglable(): void
    {
        $c = new StockCoverageCalculator(seuilRupturePct: 0.8, couvertureJoursRupture: 2, couvertureJoursAlerte: 7);

        $this->assertSame(StockStatus::Rupture, $c->fromOpenOrders(1000, 700)->status); // 70 % < 80 %
    }
}
