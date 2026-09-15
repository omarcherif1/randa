<?php

namespace Tests\Unit\Domain;

use App\Domain\Scope\OrderScope;
use App\Domain\Scope\OrderScopeClassifier;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.2 — séparation stricte Export / Local par le préfixe du numéro.
 */
class OrderScopeClassifierTest extends TestCase
{
    private OrderScopeClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new OrderScopeClassifier;
    }

    public function test_cm_est_local(): void
    {
        $this->assertSame(OrderScope::Local, $this->classifier->scope('CMRAN012605170'));
        $this->assertSame(OrderScope::Local, $this->classifier->scope('CMSMT012608966'));
    }

    public function test_exp_est_export(): void
    {
        $this->assertSame(OrderScope::Export, $this->classifier->scope('EXPRAN01260138'));
        $this->assertSame(OrderScope::Export, $this->classifier->scope('EXPSMT01260007'));
    }

    public function test_extrait_le_code_societe_du_numero(): void
    {
        $this->assertSame('RAN', $this->classifier->societeCode('CMRAN012605170'));
        $this->assertSame('SMT', $this->classifier->societeCode('EXPSMT01260007'));
        $this->assertNull($this->classifier->societeCode('X-9999'));
    }

    public function test_numero_non_reconnu_est_signale(): void
    {
        $this->assertFalse($this->classifier->isRecognized('FACRAN012606084'));
        // par prudence, traité comme local (et signalé en amont comme anomalie)
        $this->assertSame(OrderScope::Local, $this->classifier->scope('FACRAN012606084'));
    }
}
