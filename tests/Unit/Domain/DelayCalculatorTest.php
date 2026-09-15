<?php

namespace Tests\Unit\Domain;

use App\Domain\Metrics\DelayCalculator;
use App\Domain\Metrics\DelayVerdict;
use App\Domain\Scope\OrderScope;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.4 — retards de livraison (seuil 48 h). Décision de l'encadreur
 * (section 10) : la date de FACTURE sert de proxy accepté pour le retard, en
 * l'absence d'une vraie date d'expédition. Défaut critique V1 : jamais de faux
 * "0 retard" — une commande pas encore facturée reste "non calculable".
 */
class DelayCalculatorTest extends TestCase
{
    public function test_export_jamais_soumis_a_la_regle_locale(): void
    {
        $calc = new DelayCalculator(seuilJours: 2);

        $v = $calc->evaluate(OrderScope::Export, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-20'));

        $this->assertSame(DelayVerdict::NON_APPLICABLE, $v->status);
        $this->assertFalse($v->isDataAvailable());
    }

    public function test_donnee_indisponible_si_pas_encore_facturee(): void
    {
        // pas un faux 0 : la commande n'a pas de date de facture pour l'instant
        $calc = new DelayCalculator(seuilJours: 2);

        $v = $calc->evaluate(OrderScope::Local, Carbon::parse('2026-05-14'), null);

        $this->assertSame(DelayVerdict::DONNEE_INDISPONIBLE, $v->status);
        $this->assertFalse($v->isLate());
        $this->assertFalse($v->isDataAvailable());
    }

    public function test_a_temps_dans_le_seuil(): void
    {
        $calc = new DelayCalculator(seuilJours: 2);

        $v = $calc->evaluate(OrderScope::Local, Carbon::parse('2026-05-14'), Carbon::parse('2026-05-16'));

        $this->assertSame(DelayVerdict::A_TEMPS, $v->status);
        $this->assertSame(2, $v->joursEcoules);
    }

    public function test_en_retard_au_dela_du_seuil(): void
    {
        $calc = new DelayCalculator(seuilJours: 2);

        $v = $calc->evaluate(OrderScope::Local, Carbon::parse('2026-05-14'), Carbon::parse('2026-05-18'));

        $this->assertSame(DelayVerdict::EN_RETARD, $v->status);
        $this->assertSame(4, $v->joursEcoules);
        $this->assertTrue($v->isLate());
    }

    public function test_meme_jour_facture_et_commande_est_a_temps_pas_une_anomalie(): void
    {
        // Contrairement au bug V1 (livraison == commande signalait "donnée non
        // exploitable"), une facturation le jour même sur la date de FACTURE
        // (champ réel, distinct) est un résultat légitime : 0 jour, à temps.
        $calc = new DelayCalculator(seuilJours: 2);

        $v = $calc->evaluate(OrderScope::Local, Carbon::parse('2026-05-14'), Carbon::parse('2026-05-14'));

        $this->assertSame(DelayVerdict::A_TEMPS, $v->status);
        $this->assertSame(0, $v->joursEcoules);
    }

    public function test_seuil_reglable(): void
    {
        $calc = new DelayCalculator(seuilJours: 5);

        $v = $calc->evaluate(OrderScope::Local, Carbon::parse('2026-05-14'), Carbon::parse('2026-05-18'));

        $this->assertSame(DelayVerdict::A_TEMPS, $v->status);
        $this->assertSame(5, $calc->seuilJours());
    }

    public function test_date_commande_absente(): void
    {
        $calc = new DelayCalculator(seuilJours: 2);

        $v = $calc->evaluate(OrderScope::Local, null, Carbon::parse('2026-05-18'));

        $this->assertSame(DelayVerdict::DONNEE_INDISPONIBLE, $v->status);
    }
}
