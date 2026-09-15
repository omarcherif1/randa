<?php

namespace Tests\Unit\Domain;

use App\Domain\Forecast\ForecastEngine;
use App\Domain\Forecast\ForecastResult;
use PHPUnit\Framework\TestCase;

/**
 * Règle 4.5 — paliers de fiabilité + calcul de prévision.
 */
class ForecastEngineTest extends TestCase
{
    private ForecastEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new ForecastEngine;
    }

    public function test_moins_d_un_mois_pas_de_prevision(): void
    {
        $r = $this->engine->prevoir([]);

        $this->assertFalse($r->aUnePrevision());
        $this->assertSame(ForecastResult::FIABILITE_INSUFFISANTE, $r->fiabilite);
        $this->assertSame(0, $r->palier);
    }

    public function test_palier_1_un_a_deux_mois_simple_moyenne_fiabilite_faible(): void
    {
        $r = $this->engine->prevoir(['2026-01' => 100, '2026-02' => 200]);

        $this->assertSame(1, $r->palier);
        $this->assertSame(ForecastResult::FIABILITE_FAIBLE, $r->fiabilite);
        $this->assertSame(150.0, $r->quantitePrevueKg);       // moyenne
        $this->assertSame('2026-03', $r->moisCible);
    }

    public function test_palier_2_trois_a_cinq_mois_tendance_hausse(): void
    {
        $r = $this->engine->prevoir(['2026-01' => 100, '2026-02' => 200, '2026-03' => 300, '2026-04' => 400]);

        $this->assertSame(2, $r->palier);
        $this->assertSame(ForecastResult::FIABILITE_MOYENNE, $r->fiabilite);
        $this->assertSame(ForecastResult::TENDANCE_HAUSSE, $r->tendance);
        $this->assertEqualsWithDelta(500.0, $r->quantitePrevueKg, 1); // projection linéaire
    }

    public function test_palier_2_tendance_baisse_et_stable(): void
    {
        $baisse = $this->engine->prevoir(['2026-01' => 400, '2026-02' => 300, '2026-03' => 200]);
        $this->assertSame(ForecastResult::TENDANCE_BAISSE, $baisse->tendance);

        $stable = $this->engine->prevoir(['2026-01' => 300, '2026-02' => 305, '2026-03' => 298, '2026-04' => 302]);
        $this->assertSame(ForecastResult::TENDANCE_STABLE, $stable->tendance);
    }

    public function test_palier_3_six_mois_sans_meme_mois_l_an_dernier(): void
    {
        $serie = [];
        foreach (range(1, 7) as $m) {
            $serie[sprintf('2026-%02d', $m)] = 1000 + $m * 10;
        }
        $r = $this->engine->prevoir($serie);

        $this->assertSame(3, $r->palier);
        $this->assertSame(ForecastResult::FIABILITE_HAUTE, $r->fiabilite);
        $this->assertFalse($r->ajustementSaisonnier);
        $this->assertStringContainsString('pas de « même mois', $r->note);
    }

    public function test_palier_3_ajustement_saisonnier_meme_mois_pese_pour_moitie(): void
    {
        // 15 mois : la cible sera 2027-04, même mois l'an dernier = 2026-04 = 9000
        $serie = [];
        foreach (range(0, 14) as $i) {
            $y = 2026 + intdiv($i, 12);
            $mth = ($i % 12) + 1;
            $serie[sprintf('%04d-%02d', $y, $mth)] = 1000; // demande stable à 1000
        }
        $serie['2026-04'] = 9000; // pic saisonnier en avril

        $r = $this->engine->prevoir($serie);

        $this->assertSame(3, $r->palier);
        $this->assertTrue($r->ajustementSaisonnier);
        // projection de tendance ~1000 ; saisonnier 9000 ; 50/50 => ~5000
        $this->assertEqualsWithDelta(5000, $r->quantitePrevueKg, 800);
        $this->assertSame('2027-04', $r->moisCible);
    }

    public function test_les_mois_manquants_comptent_comme_demande_nulle(): void
    {
        // 3 mois de span (jan, [fev absent = 0], mars) => palier 2
        $r = $this->engine->prevoir(['2026-01' => 600, '2026-03' => 0]);

        $this->assertSame(3, $r->moisHistorique);
        $this->assertSame(2, $r->palier);
    }

    public function test_la_prevision_n_est_jamais_negative(): void
    {
        $r = $this->engine->prevoir(['2026-01' => 300, '2026-02' => 150, '2026-03' => 20, '2026-04' => 0]);

        $this->assertGreaterThanOrEqual(0, $r->quantitePrevueKg);
    }

    public function test_prevoir_jusqua_projette_chaque_mois_restant_jusqu_a_la_cible(): void
    {
        // historique jusqu'à 2026-08 (8 mois) : palier 3, prévision jusqu'à
        // décembre => 4 mois projetés (09, 10, 11, 12), un par un.
        $serie = [];
        foreach (range(1, 8) as $m) {
            $serie[sprintf('2026-%02d', $m)] = 1000 + $m * 10;
        }

        $resultats = $this->engine->prevoirJusqua($serie, '2026-12');

        $this->assertCount(4, $resultats);
        $this->assertSame(['2026-09', '2026-10', '2026-11', '2026-12'], array_map(fn ($r) => $r->moisCible, $resultats));
        // même palier (longueur d'historique fixe) pour tous les mois projetés
        foreach ($resultats as $r) {
            $this->assertSame(3, $r->palier);
        }
        // la tendance à la hausse continue de progresser mois après mois
        $this->assertGreaterThan($resultats[0]->quantitePrevueKg, $resultats[3]->quantitePrevueKg);
    }

    public function test_prevoir_jusqua_le_premier_mois_projete_correspond_a_prevoir(): void
    {
        $serie = ['2026-01' => 100, '2026-02' => 200, '2026-03' => 300, '2026-04' => 400];

        $simple = $this->engine->prevoir($serie);
        $liste = $this->engine->prevoirJusqua($serie, '2026-05');

        $this->assertCount(1, $liste);
        $this->assertSame($simple->moisCible, $liste[0]->moisCible);
        $this->assertSame($simple->quantitePrevueKg, $liste[0]->quantitePrevueKg);
    }

    public function test_prevoir_jusqua_rien_a_projeter_si_historique_deja_au_dela(): void
    {
        $serie = ['2026-01' => 100, '2026-02' => 200];

        $this->assertSame([], $this->engine->prevoirJusqua($serie, '2026-01'));
        $this->assertSame([], $this->engine->prevoirJusqua([], '2026-12'));
    }

    public function test_prevoir_jusqua_ajustement_saisonnier_par_mois_cible(): void
    {
        // 15 mois stables à 1000, avec un pic en avril ET un creux en octobre
        // l'an dernier : chaque mois projeté doit utiliser SON PROPRE "même
        // mois l'an dernier", pas celui du dernier mois observé.
        $serie = [];
        foreach (range(0, 14) as $i) {
            $y = 2026 + intdiv($i, 12);
            $mth = ($i % 12) + 1;
            $serie[sprintf('%04d-%02d', $y, $mth)] = 1000;
        }
        $serie['2026-10'] = 200; // creux saisonnier en octobre l'an dernier

        $resultats = $this->engine->prevoirJusqua($serie, '2027-10');
        $octobre = collect($resultats)->firstWhere('moisCible', '2027-10');

        $this->assertNotNull($octobre);
        $this->assertTrue($octobre->ajustementSaisonnier);
        // tendance ~1000, saisonnier 200 => 50/50 nettement sous 1000
        $this->assertLessThan(700, $octobre->quantitePrevueKg);
    }
}
