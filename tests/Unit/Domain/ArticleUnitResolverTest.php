<?php

namespace Tests\Unit\Domain;

use App\Domain\Units\ArticleUnitResolver;
use App\Domain\Units\UnitWeightOrigin;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.8 — poids unitaire article par article, jamais un facteur global.
 * Couvre la table de cas obligatoire du cahier des charges.
 */
class ArticleUnitResolverTest extends TestCase
{
    private function resolver(array $map = []): ArticleUnitResolver
    {
        return new ArticleUnitResolver($map, quintalHypotheseKg: 100.0);
    }

    /**
     * @dataProvider casObligatoires
     */
    public function test_table_de_cas_obligatoire(
        string $designation,
        string $unite,
        float $quantiteLigne,
        ?float $poidsAttendu,
        float $totalAttendu,
        string $origineAttendue,
    ): void {
        $r = $this->resolver()->resolve('ART', $designation, $unite);

        $this->assertSame($poidsAttendu, $r->poidsUnitaireKg, 'poids unitaire');
        $this->assertSame($origineAttendue, $r->origine, 'origine');
        $this->assertSame($poidsAttendu === null ? null : $totalAttendu, $r->convertir($quantiteLigne), 'total converti');
    }

    public static function casObligatoires(): array
    {
        return [
            ['Semoule_fine_20KG', 'SAC', 50, 20.0, 1000.0, UnitWeightOrigin::EXTRAIT_DESIGNATION],
            ['Semoule_fine_10 KG', 'SAC', 50, 10.0, 500.0, UnitWeightOrigin::EXTRAIT_DESIGNATION],
            ['Semoule_fine_5 KG', 'SAC', 50, 5.0, 250.0, UnitWeightOrigin::EXTRAIT_DESIGNATION],
            ['Farine Spéciale 50 KG', 'SAC', 30, 50.0, 1500.0, UnitWeightOrigin::EXTRAIT_DESIGNATION],
            ['Semoule SSSE 50 Kg AT', 'T', 240, 50.0, 12000.0, UnitWeightOrigin::EXTRAIT_DESIGNATION],
            ['Farine PS-7 1 KG AT', 'T', 4, 1.0, 4.0, UnitWeightOrigin::EXTRAIT_DESIGNATION],
            ['Farine PS en sac perdus', 'QX', 28, 100.0, 2800.0, UnitWeightOrigin::HYPOTHESE_QUINTAL],
            ['Nimporte quel article', 'KG', 1200, 1.0, 1200.0, UnitWeightOrigin::KG_NEUTRE],
            // Confirmé par l'entreprise : QX n'a pas de facteur unique — « Tchich »
            // pèse 25 kg l'unité, pas l'hypothèse 100 kg (règle 2.8).
            ['Tchich_25KG', 'QX', 40, 25.0, 1000.0, UnitWeightOrigin::EXTRAIT_DESIGNATION],
        ];
    }

    public function test_aucun_poids_trouvable_ligne_exclue_des_tonnages_sans_erreur(): void
    {
        $r = $this->resolver()->resolve('ARTX', 'PRODUIT VRAC SANS INDICATION', 'SAC');

        $this->assertNull($r->poidsUnitaireKg);
        $this->assertSame(UnitWeightOrigin::NON_DETERMINE, $r->origine);
        $this->assertFalse($r->compteVolume());
        $this->assertNull($r->convertir(10)); // pas de 0 silencieux
    }

    public function test_quintal_marque_le_volume_comme_non_fiable_quand_le_poids_est_une_hypothese(): void
    {
        $r = $this->resolver()->resolve('ARTQ', 'Farine PS en sac perdus', 'QX');

        $this->assertTrue($r->compteVolume());
        $this->assertFalse($r->volumeFiable());
    }

    public function test_quintal_avec_poids_extractible_est_fiable_pas_une_hypothese(): void
    {
        // Il n'existe pas de facteur unique pour QX : chaque article a son propre
        // poids, exactement comme SAC et T (décision entreprise, article 501008).
        $r = $this->resolver()->resolve('501008', 'Tchich_25KG', 'QX');

        $this->assertSame(25.0, $r->poidsUnitaireKg);
        $this->assertSame(UnitWeightOrigin::EXTRAIT_DESIGNATION, $r->origine);
        $this->assertTrue($r->volumeFiable());
        $this->assertFalse($r->enConflit);
    }

    public function test_kg_et_extraction_donnent_un_volume_fiable(): void
    {
        $this->assertTrue($this->resolver()->resolve('A', 'x', 'KG')->volumeFiable());
        $this->assertTrue($this->resolver()->resolve('A', 'Semoule_fine_20KG', 'SAC')->volumeFiable());
    }

    public function test_conflit_garde_l_ancienne_valeur_validee_et_signale(): void
    {
        $map = ['ART' => [
            'poids' => 20.0,
            'origine' => UnitWeightOrigin::EXTRAIT_DESIGNATION,
            'confirme' => true,
            'designation_ref' => 'Semoule_fine_20KG',
        ]];

        $resolver = $this->resolver($map);
        $r = $resolver->resolve('ART', 'Semoule_fine_50KG', 'SAC'); // le texte a changé

        $this->assertSame(20.0, $r->poidsUnitaireKg, 'on garde la valeur validée');
        $this->assertTrue($r->enConflit);
        $this->assertCount(1, $resolver->conflicts());
        $this->assertSame(50.0, $resolver->conflicts()[0]['poids_extrait_kg']);
    }

    public function test_valeur_deja_extraite_est_reutilisee_sans_re_analyse(): void
    {
        $map = ['ART' => [
            'poids' => 20.0,
            'origine' => UnitWeightOrigin::EXTRAIT_DESIGNATION,
            'confirme' => true,
            'designation_ref' => 'Semoule_fine_20KG',
        ]];

        // même poids dans le nouveau texte => pas de conflit
        $r = $this->resolver($map)->resolve('ART', 'Semoule fine 20 KG (nouveau libellé)', 'SAC');

        $this->assertSame(20.0, $r->poidsUnitaireKg);
        $this->assertFalse($r->enConflit);
    }

    public function test_correction_manuelle_du_quintal_prime_sur_l_hypothese(): void
    {
        $map = ['ARTQ' => [
            'poids' => 90.0,
            'origine' => UnitWeightOrigin::CORRECTION_MANUELLE,
            'confirme' => true,
            'designation_ref' => null,
        ]];

        $r = $this->resolver($map)->resolve('ARTQ', 'Farine PS en sac perdus', 'QX');

        $this->assertSame(90.0, $r->poidsUnitaireKg);
        $this->assertSame(UnitWeightOrigin::CORRECTION_MANUELLE, $r->origine);
    }

    public function test_pending_weights_accumule_ce_qui_doit_etre_persiste(): void
    {
        $resolver = $this->resolver();
        $resolver->resolve('A1', 'Semoule_fine_20KG', 'SAC');
        $resolver->resolve('A2', 'Farine PS en sac perdus', 'QX');
        $resolver->resolve('A3', 'SANS POIDS', 'SAC');

        $pending = $resolver->pendingWeights();
        $this->assertSame(20.0, $pending['A1']['poids']);
        $this->assertSame(UnitWeightOrigin::HYPOTHESE_QUINTAL, $pending['A2']['origine']);
        $this->assertNull($pending['A3']['poids']);
    }
}
