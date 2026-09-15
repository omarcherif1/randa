<?php

namespace Tests\Feature\Domain;

use App\Domain\Import\ImportManager;
use App\Models\Anomalie;
use App\Models\ArticleFamily;
use App\Models\ArticleUnitWeight;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\ImportBatch;
use App\Models\Produit;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Étape 2 — moteur d'import du fichier unifié : dédoublonnage par remplacement,
 * journalisation, détection automatique des anomalies.
 */
class UnifiedFileImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([
            SocieteSeeder::class, FamilleSeeder::class, ClientCategorySeeder::class,
            SettingSeeder::class, ClassificationKeywordSeeder::class,
        ]);
        $this->fixture = base_path('tests/Fixtures/unified_sample.csv');
    }

    private function import(): ImportBatch
    {
        return app(ImportManager::class)->import($this->fixture, 'unified_sample.csv');
    }

    public function test_import_complet_cree_le_journal_et_les_donnees(): void
    {
        $batch = $this->import();

        $this->assertSame('completed', $batch->status);
        $this->assertSame(18, $batch->rows_total);
        $this->assertSame(17, $batch->commandes_new); // 17 numéros de commande distincts dans la fixture
        $this->assertSame(0, $batch->commandes_replaced);
        $this->assertSame(18, $batch->lignes_count);
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);

        $this->assertSame(17, Commande::count());
        $this->assertSame(18, CommandeLigne::count());
        $this->assertGreaterThan(0, Client::count());
        $this->assertGreaterThan(0, Produit::count());
    }

    public function test_reimport_du_meme_fichier_remplace_sans_additionner_regle_4_7(): void
    {
        $this->import();
        $commandesApres1 = Commande::count();
        $lignesApres1 = CommandeLigne::count();
        $qteApres1 = Commande::where('numero', 'CMRAN012605170')->value('qte_livree_kg');

        $batch2 = $this->import();

        $this->assertSame(0, $batch2->commandes_new);
        $this->assertSame(17, $batch2->commandes_replaced);
        $this->assertSame($commandesApres1, Commande::count(), 'aucune commande dupliquée');
        $this->assertSame($lignesApres1, CommandeLigne::count(), 'aucune ligne additionnée');
        $this->assertSame($qteApres1, Commande::where('numero', 'CMRAN012605170')->value('qte_livree_kg'));
    }

    public function test_scope_export_est_marque(): void
    {
        $this->import();

        $this->assertSame('export', Commande::where('numero', 'EXPRAN01260138')->value('scope'));
        $this->assertSame('local', Commande::where('numero', 'CMRAN012605170')->value('scope'));
    }

    public function test_exclusions_regle_2_1_sont_persistees(): void
    {
        $this->import();

        $vide = CommandeLigne::whereHas('commande', fn ($q) => $q->where('numero', 'CMRAN012605206'))->first();
        $this->assertSame('ligne_vide', $vide->exclusion_reason);
        $this->assertFalse($vide->is_eligible);

        $this->assertTrue(Commande::where('numero', 'CMRAN012605206')->value('is_excluded'));
    }

    public function test_valeur_aberrante_genere_une_anomalie_et_sort_des_totaux(): void
    {
        $this->import();

        $ligne = CommandeLigne::whereHas('commande', fn ($q) => $q->where('numero', 'CMSMT012608966'))->first();
        $this->assertTrue($ligne->is_outlier);
        $this->assertFalse($ligne->compte_volume);

        $this->assertDatabaseHas('anomalies', ['type' => 'outlier_quantite', 'article' => '900111']);
        $this->assertTrue(Commande::where('numero', 'CMSMT012608966')->value('has_outlier'));
    }

    public function test_poids_unitaire_memorise_par_article_regle_2_8(): void
    {
        $this->import();

        $this->assertSame(50.0, ArticleUnitWeight::where('article', '900112')->value('poids_unitaire_kg'));
        $this->assertSame('extrait_designation', ArticleUnitWeight::where('article', '900112')->value('origine'));
        // QX : hypothèse, non fiable
        $this->assertSame('hypothese_quintal', ArticleUnitWeight::where('article', '900114')->value('origine'));
        // SAC sans indication : non déterminé + anomalie
        $this->assertNull(ArticleUnitWeight::where('article', '900115')->value('poids_unitaire_kg'));
        $this->assertDatabaseHas('anomalies', ['type' => 'poids_unitaire_indetermine', 'article' => '900115']);
    }

    public function test_quantite_facturee_n_est_pas_reconvertie_regle_2_8(): void
    {
        // « Qté facturée US » du fichier source est DÉJÀ en kg (constaté sur le fichier
        // réel — voir docs/DATA_NOTES.md point 11), contrairement à commandée/livrée qui
        // sont dans l'unité de vente brute (SAC/T/QX/KG) et ont besoin du poids unitaire
        // (règle 2.8) pour être converties. La ligne article 900112 (SAC, 50 kg/unité,
        // qte_facturee brute = 30 dans la fixture) ne doit JAMAIS devenir 30 × 50 = 1500.
        $this->import();

        $ligne = CommandeLigne::where('article', '900112')->first();
        $this->assertSame(30.0, $ligne->qte_facturee_raw);
        $this->assertSame(30.0, $ligne->qte_facturee_kg, 'facturée ne doit pas être multipliée par le poids unitaire');
    }

    public function test_familles_consolidees_par_code_article_complet_regle_2_6(): void
    {
        $this->import();

        $this->assertSame('confirme', ArticleFamily::where('article', '113231')->value('statut'));
        $this->assertSame('reclassification', ArticleFamily::where('article', '134237')->value('statut'));
        $this->assertSame('121', ArticleFamily::where('article', '134237')->value('famille_code'));

        $ambigu = ArticleFamily::where('article', '514127')->first();
        $this->assertSame('ambigu', $ambigu->statut);
        $this->assertNull($ambigu->famille_code);
        $this->assertDatabaseHas('anomalies', ['type' => 'famille_ambigue', 'article' => '514127']);
        $this->assertDatabaseHas('anomalies', ['type' => 'famille_inconnue', 'article' => '999999']);
    }

    public function test_client_classifie_une_seule_fois_par_numero_regle_2_7(): void
    {
        $this->import();

        $monoprix = Client::where('numero', '15015K')->first();
        $this->assertSame('keyword', $monoprix->category_source);
        $this->assertSame('Grande Surface', $monoprix->category->intitule);
        $this->assertSame(1, Client::where('numero', '15015K')->count());
    }

    public function test_etat_du_cycle_de_vie_est_derive(): void
    {
        $this->import();

        // toutes lignes Soldé + facture => clôturée
        $this->assertSame('cloturee', Commande::where('numero', 'CMRAN012605170')->value('etat'));
        // pas de livraison ni facture => saisie
        $this->assertSame('saisie', Commande::where('numero', 'CMRAN012605207')->value('etat'));
    }

    public function test_anomalie_deja_tranchee_par_admin_n_est_pas_recreee(): void
    {
        $this->import();
        $anomalie = Anomalie::where('type', 'famille_ambigue')->where('article', '514127')->firstOrFail();
        $anomalie->update(['statut' => Anomalie::STATUT_VALIDEE]);

        $this->import();

        $this->assertSame(
            1,
            Anomalie::where('type', 'famille_ambigue')->where('article', '514127')->count(),
            'la décision admin est respectée, pas de doublon',
        );
        $this->assertSame(
            Anomalie::STATUT_VALIDEE,
            Anomalie::where('type', 'famille_ambigue')->where('article', '514127')->value('statut'),
        );
    }

    public function test_fichier_invalide_est_refuse_proprement(): void
    {
        $bad = base_path('tests/Fixtures/_bad.csv');
        file_put_contents($bad, "colonne1;colonne2\nx;y\n");

        $batch = app(ImportManager::class)->import($bad, 'bad.csv');
        @unlink($bad);

        $this->assertSame('failed', $batch->status);
        $this->assertNotEmpty($batch->error_message);
        $this->assertSame(0, Commande::count());
    }
}
