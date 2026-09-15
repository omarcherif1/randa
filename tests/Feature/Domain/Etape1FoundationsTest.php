<?php

namespace Tests\Feature\Domain;

use App\Domain\Classification\FamilyColumnDetector;
use App\Domain\Classification\FamilyConsolidation;
use App\Domain\Import\ColumnMap;
use App\Domain\Import\CsvReader;
use App\Domain\Import\ReferenceData;
use App\Domain\Import\RowNormalizer;
use App\Domain\Metrics\SatisfactionCalculator;
use App\Domain\Quality\ExclusionReason;
use App\Domain\Quality\OutlierDetector;
use App\Domain\Scope\OrderScope;
use App\Domain\Units\UnitWeightOrigin;
use App\Models\ClientCategory;
use App\Models\Famille;
use App\Models\Setting;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Étape 1 — le socle de données applique fidèlement les règles de la section 2
 * sur un échantillon représentatif du fichier source réel.
 */
class Etape1FoundationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SocieteSeeder::class,
            FamilleSeeder::class,
            ClientCategorySeeder::class,
            SettingSeeder::class,
            ClassificationKeywordSeeder::class,
        ]);
    }

    public function test_les_tables_de_reference_sont_completes(): void
    {
        $this->assertSame(32, Famille::count(), '32 familles officielles (règle 2.6)');
        $this->assertSame(21, ClientCategory::count(), '21 catégories client (règle 2.7)');
        $this->assertSame(1, ClientCategory::where('is_export', true)->count());
        $this->assertSame(1, ClientCategory::where('is_fallback', true)->count());
        $this->assertSame(100, Setting::get('qx_poids_kg_hypothese'));
    }

    private function normalizedLines(): array
    {
        $path = base_path('tests/Fixtures/unified_sample.csv');
        $reader = new CsvReader($path);
        $header = $reader->header();

        $ref = ReferenceData::load();

        $sample = iterator_to_array($reader->rows(50), false);
        $detection = (new FamilyColumnDetector)->detect($header, $sample, $ref->familleCodes);

        $columns = (new ColumnMap($header))->withFamilyColumn($detection->chosenIndex);

        $familyResolver = $ref->familyResolver();
        $normalizer = new RowNormalizer(
            columns: $columns,
            ref: $ref,
            units: $ref->articleUnitResolver(),
            familyResolver: $familyResolver,
            clientClassifier: $ref->clientClassifier(),
            outliers: OutlierDetector::fromSettings(),
        );

        $lines = [];
        foreach ($reader->rows() as $row) {
            $lines[] = $normalizer->normalize($row);
        }

        return [$lines, $detection, $familyResolver];
    }

    public function test_detecte_la_bonne_colonne_famille_et_signale_l_ambiguite(): void
    {
        [$lines, $detection] = $this->normalizedLines();

        $this->assertNotNull($detection->chosenIndex);
        // col 18 = 2e "Famille statistique" : celle qui porte les vrais codes pôle produit
        $this->assertSame(18, $detection->chosenIndex);
    }

    public function test_separation_export_local_par_prefixe_de_numero(): void
    {
        [$lines] = $this->normalizedLines();

        $exports = array_filter($lines, fn ($l) => $l->scope === OrderScope::Export);
        $this->assertCount(1, $exports);
        $this->assertSame('EXPRAN01260138', array_values($exports)[0]->numeroCommande);
    }

    public function test_exclusions_regle_2_1(): void
    {
        [$lines] = $this->normalizedLines();
        $byOrder = [];
        foreach ($lines as $l) {
            $byOrder[$l->numeroCommande][] = $l;
        }

        // ligne vide
        $this->assertSame(ExclusionReason::LigneVide, $byOrder['CMRAN012605206'][0]->exclusionReason);
        // commande ouverte sans livraison
        $this->assertSame(ExclusionReason::OuverteSansLivraison, $byOrder['CMRAN012605207'][0]->exclusionReason);
        // mouvement interne (commandé 0 / livré > 0)
        $this->assertSame(ExclusionReason::MouvementInterne, $byOrder['CMRAN012605205'][0]->exclusionReason);
    }

    public function test_valeur_aberrante_regle_2_9(): void
    {
        [$lines] = $this->normalizedLines();

        $aberrantes = array_filter($lines, fn ($l) => $l->isOutlier);
        $this->assertCount(1, $aberrantes);
        $this->assertSame('CMSMT012608966', array_values($aberrantes)[0]->numeroCommande);
        // exclue des lignes retenues
        $this->assertFalse(array_values($aberrantes)[0]->comptabilisee());
    }

    public function test_conversion_unites_regle_2_8(): void
    {
        [$lines] = $this->normalizedLines();
        $byOrder = [];
        foreach ($lines as $l) {
            $byOrder[$l->numeroCommande][] = $l;
        }

        // KG : 1200 => 1200 kg, aucune conversion
        $kg = $byOrder['CMRAN012605170'][0];
        $this->assertSame(1200.0, $kg->qteLivreeKg);
        $this->assertSame(UnitWeightOrigin::KG_NEUTRE, $kg->poidsOrigine);

        // SAC + poids extrait de la désignation ("Farine Spéciale 50 KG") : 30 sacs => 1500 kg
        $sac = $byOrder['CMSMT012608967'][0];
        $this->assertSame(50.0, $sac->poidsUnitaireKg);
        $this->assertSame(1500.0, $sac->qteLivreeKg);
        $this->assertSame(UnitWeightOrigin::EXTRAIT_DESIGNATION, $sac->poidsOrigine);
        $this->assertTrue($sac->compteVolume);
        $this->assertTrue($sac->volumeFiable);

        // "T" = nombre de sacs de 50 kg, PAS des tonnes : 240 => 12 000 kg
        $t = $byOrder['CMSMT012608968'][0];
        $this->assertSame(50.0, $t->poidsUnitaireKg);
        $this->assertSame(12000.0, $t->qteLivreeKg);

        // QX : hypothèse 100 kg/quintal, converti mais volume NON fiable
        $qx = $byOrder['CMSMT012608969'][0];
        $this->assertSame(2800.0, $qx->qteLivreeKg);
        $this->assertSame(UnitWeightOrigin::HYPOTHESE_QUINTAL, $qx->poidsOrigine);
        $this->assertTrue($qx->compteVolume);
        $this->assertFalse($qx->volumeFiable);

        // Aucun poids trouvable : ligne EXCLUE des tonnages, jamais un 0 silencieux
        $inconnu = $byOrder['CMSMT012608970'][0];
        $this->assertNull($inconnu->qteLivreeKg);
        $this->assertSame(UnitWeightOrigin::NON_DETERMINE, $inconnu->poidsOrigine);
        $this->assertFalse($inconnu->compteVolume);
        // mais reste comptée pour la satisfaction (comparaison dans l'unité d'origine)
        $this->assertTrue($inconnu->isEligible);
        $this->assertTrue($inconnu->isSatisfied);
    }

    public function test_tonnage_separe_le_fiable_de_l_hypothese(): void
    {
        [$lines] = $this->normalizedLines();

        $fiable = 0.0;
        $aConfirmer = 0.0;
        $exclues = 0;
        foreach ($lines as $l) {
            if (! $l->compteVolume) {
                if ($l->poidsOrigine === UnitWeightOrigin::NON_DETERMINE) {
                    $exclues++;
                }

                continue;
            }
            $l->volumeFiable ? $fiable += $l->qteLivreeKg : $aConfirmer += $l->qteLivreeKg;
        }

        $this->assertSame(1, $exclues, '1 ligne sans poids déterminable exclue des tonnages');
        $this->assertSame(2800.0, $aConfirmer, 'la ligne QX est isolée comme "à confirmer"');
        $this->assertGreaterThan(0, $fiable);
    }

    public function test_satisfaction_par_societe_jamais_moyennee_regle_2_3(): void
    {
        [$lines] = $this->normalizedLines();

        $calc = new SatisfactionCalculator;
        $result = $calc->compute(array_map(fn ($l) => [
            'societe_code' => $l->societeCode ?? '??',
            'eligible' => $l->scope === OrderScope::Local && $l->isEligible,
            'satisfaite' => $l->isSatisfied,
        ], $lines));

        // RAN local : 9 lignes éligibles (3 exclusions écartées), seule LASAGNE 120/60 non satisfaite
        $this->assertSame(round(8 / 9, 4), $result->taux('RAN'));
        // SMT local : outlier exclu, 4 lignes toutes servies
        $this->assertSame(1.0, $result->taux('SMT'));
    }

    public function test_famille_inconnue_isolee_pas_masquee_regle_2_6(): void
    {
        [$lines] = $this->normalizedLines();

        $inconnues = array_filter($lines, fn ($l) => $l->familleInconnue());
        $this->assertCount(1, $inconnues);
        $this->assertSame('CMRAN012605208', array_values($inconnues)[0]->numeroCommande);
    }

    public function test_consolidation_famille_par_code_article_complet_regle_2_6(): void
    {
        [, , $familyResolver] = $this->normalizedLines();
        $cons = $familyResolver->consolidate();

        // 113231 : toujours 106 => confirmé
        $this->assertSame(FamilyConsolidation::STATUT_CONFIRME, $cons['113231']->statut);
        $this->assertSame('106', $cons['113231']->familleCode);

        // 134237 : 107 en janvier puis 121 dès juin (plages disjointes) => reclassification, on garde 121
        $this->assertSame(FamilyConsolidation::STATUT_RECLASSIFICATION, $cons['134237']->statut);
        $this->assertSame('121', $cons['134237']->familleCode);

        // 514127 : 101 et 130 entremêlés sans logique de date => ambigu, aucun choix automatique
        $this->assertSame(FamilyConsolidation::STATUT_AMBIGU, $cons['514127']->statut);
        $this->assertNull($cons['514127']->familleCode);
    }

    public function test_classification_client_par_mot_cle_regle_2_7(): void
    {
        [$lines] = $this->normalizedLines();
        $ref = ReferenceData::load();
        $classifier = $ref->clientClassifier();

        $monoprix = $classifier->classify('15015K', 'STE MONOPRIX LAC');
        $this->assertSame('107', $monoprix->categoryCode); // Grande Surface

        $boul = $classifier->classify('2840253', 'BOULANGERIE DU COIN');
        $this->assertSame('108', $boul->categoryCode); // Boulanger
    }

    public function test_setting_null_reste_null(): void
    {
        // régression : famille_colonne_index seedé à null ne doit pas devenir 0
        $this->assertNull(Setting::get('famille_colonne_index'));
    }
}
