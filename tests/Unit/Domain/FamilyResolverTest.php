<?php

namespace Tests\Unit\Domain;

use App\Domain\Classification\FamilyConsolidation;
use App\Domain\Classification\FamilyResolution;
use App\Domain\Classification\FamilyResolver;
use PHPUnit\Framework\TestCase;

/**
 * Règle 2.6 — famille via le code article COMPLET, complétion par mémoire,
 * catégorie explicite "inconnue", consolidation des articles multi-familles.
 */
class FamilyResolverTest extends TestCase
{
    private array $validCodes = ['100', '101', '106', '107', '113', '121', '130'];

    public function test_utilise_le_code_du_fichier_quand_il_est_valide(): void
    {
        $r = (new FamilyResolver($this->validCodes))->resolve('106', '113231');

        $this->assertSame('106', $r->code);
        $this->assertSame(FamilyResolution::SOURCE_FILE, $r->source);
    }

    public function test_complete_depuis_la_memoire_quand_la_ligne_est_vide(): void
    {
        $resolver = new FamilyResolver($this->validCodes, ['113231' => '106']);

        $r = $resolver->resolve(null, '113231');

        $this->assertSame('106', $r->code);
        $this->assertSame(FamilyResolution::SOURCE_BACKFILLED, $r->source);
    }

    public function test_famille_inconnue_quand_l_article_n_a_jamais_eu_de_famille_valide(): void
    {
        $r = (new FamilyResolver($this->validCodes))->resolve('999', 'NOUVEAU');

        $this->assertNull($r->code);
        $this->assertSame(FamilyResolution::SOURCE_UNKNOWN, $r->source);
        $this->assertFalse($r->isKnown());
    }

    public function test_jamais_de_deduction_par_prefixe_du_code_article(): void
    {
        // deux articles, même préfixe "134", familles différentes
        $resolver = new FamilyResolver($this->validCodes);
        $resolver->resolve('107', '134237');
        $resolver->resolve('121', '134238');

        // un 3e article au même préfixe, sans famille sur la ligne => INCONNUE, pas "107" ni "121"
        $r = $resolver->resolve('', '134999');

        $this->assertNull($r->code);
        $this->assertSame(FamilyResolution::SOURCE_UNKNOWN, $r->source);
    }

    public function test_consolidation_article_a_famille_unique(): void
    {
        $resolver = new FamilyResolver($this->validCodes);
        $resolver->resolve('106', '113231', '2026-01-10');
        $resolver->resolve('106', '113231', '2026-05-02');

        $cons = $resolver->consolidate()['113231'];

        $this->assertSame(FamilyConsolidation::STATUT_CONFIRME, $cons->statut);
        $this->assertSame('106', $cons->familleCode);
    }

    public function test_consolidation_reclassification_dans_le_temps_garde_la_plus_recente(): void
    {
        $resolver = new FamilyResolver($this->validCodes);
        // "Nouasser" : 107 jusqu'en mars, puis 121 à partir d'avril (plages disjointes)
        $resolver->resolve('107', '134237', '2026-01-02');
        $resolver->resolve('107', '134237', '2026-03-30');
        $resolver->resolve('121', '134237', '2026-04-06');
        $resolver->resolve('121', '134237', '2026-08-19');

        $cons = $resolver->consolidate()['134237'];

        $this->assertSame(FamilyConsolidation::STATUT_RECLASSIFICATION, $cons->statut);
        $this->assertSame('121', $cons->familleCode);
        $this->assertCount(2, $cons->historique);
    }

    public function test_consolidation_ambigue_si_pas_de_correlation_temporelle(): void
    {
        $resolver = new FamilyResolver($this->validCodes);
        // familles entremêlées : 101 et 130 alternent sans logique de date
        $resolver->resolve('101', '514127', '2026-01-02');
        $resolver->resolve('130', '514127', '2026-04-09');
        $resolver->resolve('101', '514127', '2026-06-01');
        $resolver->resolve('130', '514127', '2026-06-22');
        $resolver->resolve('101', '514127', '2026-08-17');

        $cons = $resolver->consolidate()['514127'];

        $this->assertSame(FamilyConsolidation::STATUT_AMBIGU, $cons->statut);
        $this->assertNull($cons->familleCode, 'aucun choix automatique');
        $this->assertTrue($cons->estAmbigu());
    }

    public function test_article_ambigu_conserve_la_valeur_deja_memorisee(): void
    {
        $resolver = new FamilyResolver($this->validCodes, ['514127' => '101']);
        $resolver->resolve('101', '514127', '2026-01-02');
        $resolver->resolve('130', '514127', '2026-04-09');
        $resolver->resolve('101', '514127', '2026-06-01');

        $cons = $resolver->consolidate()['514127'];

        $this->assertSame(FamilyConsolidation::STATUT_AMBIGU, $cons->statut);
        $this->assertSame('101', $cons->familleCode, 'on garde la valeur validée, on ne tranche pas');
    }

    public function test_ne_recalcule_pas_depuis_zero_les_lignes_deja_completees_par_memoire(): void
    {
        // une ligne complétée par mémoire n'ajoute pas d'observation
        $resolver = new FamilyResolver($this->validCodes, ['113231' => '106']);
        $resolver->resolve(null, '113231', '2026-05-02');

        $this->assertArrayNotHasKey('113231', $resolver->consolidate());
    }
}
