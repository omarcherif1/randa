<?php

namespace Tests\Unit\Domain;

use App\Domain\Quality\ExclusionReason;
use App\Domain\Quality\LineExclusionEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.1 — qu'est-ce qu'une "vraie commande" ?
 */
class LineExclusionEvaluatorTest extends TestCase
{
    private LineExclusionEvaluator $eval;

    protected function setUp(): void
    {
        $this->eval = new LineExclusionEvaluator;
    }

    public function test_ligne_normale_n_est_pas_exclue(): void
    {
        $this->assertNull($this->eval->evaluate(1200, 1200));
        $this->assertNull($this->eval->evaluate(120, 60)); // partiellement livrée mais réelle
    }

    public function test_mouvement_interne_commande_zero_livre_positif(): void
    {
        $this->assertSame(
            ExclusionReason::MouvementInterne,
            $this->eval->evaluate(0, 340),
        );
    }

    public function test_ligne_totalement_vide(): void
    {
        $this->assertSame(
            ExclusionReason::LigneVide,
            $this->eval->evaluate(0, 0),
        );
    }

    public function test_commande_ouverte_sans_aucune_livraison(): void
    {
        $this->assertSame(
            ExclusionReason::OuverteSansLivraison,
            $this->eval->evaluate(1000, 0),
        );
    }

    public function test_hors_perimetre_si_client_societe_du_groupe(): void
    {
        // règle objective (défaut V1 corrigé) : plus de dépendance à un champ jamais rempli
        $this->assertSame(
            ExclusionReason::HorsPerimetre,
            $this->eval->evaluate(500, 500, clientEstSocieteGroupe: true),
        );
    }

    public function test_toutes_les_raisons_excluent_de_tous_les_indicateurs(): void
    {
        foreach (ExclusionReason::cases() as $reason) {
            $this->assertTrue($reason->excludesFromAllMetrics());
        }
    }
}
