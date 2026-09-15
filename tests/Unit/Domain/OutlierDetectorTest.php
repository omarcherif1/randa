<?php

namespace Tests\Unit\Domain;

use App\Domain\Quality\OutlierDetector;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.9 — filtrage des valeurs aberrantes.
 */
class OutlierDetectorTest extends TestCase
{
    private function detector(): OutlierDetector
    {
        return new OutlierDetector(
            plafondKgParLigne: 2_000_000,
            ratioCommandeLivreeMax: 1000,
            ratioEcartMedianeMax: 25,
        );
    }

    public function test_ligne_plausible_passe(): void
    {
        $this->assertFalse($this->detector()->inspectRow(1200, 1200)->isOutlier);
        $this->assertFalse($this->detector()->inspectRow(300_000, 300_000)->isOutlier); // gros export légitime
    }

    public function test_quantite_hors_plafond_est_signalee(): void
    {
        $v = $this->detector()->inspectRow(10_025_200_250, 4950); // le cas "200 504 005 SAC"
        $this->assertTrue($v->isOutlier);
        $this->assertSame('quantite_hors_plafond', $v->reason);
    }

    public function test_ecart_commande_livraison_impossible(): void
    {
        // 200 504 005 commandé / 99 livré => erreur de saisie sur la commande
        $v = $this->detector()->inspectRow(200_504_005 * 0.001, 99 * 0.001);
        $this->assertTrue($v->isOutlier);
        $this->assertSame('ratio_commande_livree_impossible', $v->reason);
    }

    public function test_quantite_negative_est_signalee(): void
    {
        $v = $this->detector()->inspectRow(-50, 0);
        $this->assertTrue($v->isOutlier);
        $this->assertSame('quantite_negative', $v->reason);
    }

    public function test_controle_statistique_par_article(): void
    {
        $quantites = [500, 520, 480, 510, 505, 495, 1_000_000]; // la dernière est aberrante
        $out = $this->detector()->statisticalOutliers($quantites);

        $this->assertCount(1, $out);
        $this->assertSame(6, $out[0]['index']);
    }

    public function test_controle_statistique_ignore_les_petits_echantillons(): void
    {
        $this->assertSame([], $this->detector()->statisticalOutliers([10, 5000]));
    }

    public function test_le_plafond_exclut_la_valeur_pile_au_seuil(): void
    {
        // Décision entreprise du 2026-09-11 (docs/DATA_NOTES.md) : les lignes
        // "Déchet" à EXACTEMENT 1 000 000 kg sont une valeur aberrante
        // confirmée. Le seuil doit être inclusif (>=), pas strict (>).
        $detector = new OutlierDetector(
            plafondKgParLigne: 1_000_000,
            ratioCommandeLivreeMax: 1000,
            ratioEcartMedianeMax: 25,
        );

        $v = $detector->inspectRow(1_000_000, 0);
        $this->assertTrue($v->isOutlier);
        $this->assertSame('quantite_hors_plafond', $v->reason);

        $this->assertFalse($detector->inspectRow(999_999, 0)->isOutlier);
    }
}
