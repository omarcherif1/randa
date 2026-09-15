<?php

namespace Tests\Feature\Forecast;

use App\Domain\Forecast\ForecastFilter;
use App\Domain\Forecast\ForecastMetrics;
use App\Domain\Forecast\ForecastSnapshotWriter;
use App\Domain\Import\ImportManager;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\ForecastSnapshot;
use App\Models\Societe;
use App\Models\User;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Étape 6 — page Prévisions (section 4.5).
 */
class ForecastPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Ces tests couvrent le calcul PHP (repli) — voir
        // MlForecastIntegrationTest pour le chemin ML actif.
        $this->fakeMlServiceDown();
        $this->seed([
            SocieteSeeder::class, FamilleSeeder::class, ClientCategorySeeder::class,
            SettingSeeder::class, ClassificationKeywordSeeder::class, PermissionTemplateSeeder::class,
        ]);
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_page_gardee_par_permission(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/previsions')->assertForbidden();
        $u->pagePermissions()->create(['page_key' => 'previsions']);
        $this->actingAs($u)->get('/previsions')->assertOk();
    }

    public function test_metrics_produit_avec_palier_backtest_et_notes(): void
    {
        $m = (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->admin()));

        $this->assertNotEmpty($m['produits']);
        $p = $m['produits'][0];
        $this->assertArrayHasKey('palier', $p['prevision']);
        $this->assertArrayHasKey('fiabilite', $p['prevision']);
        $this->assertArrayHasKey('mape', $p['backtest']);
        $this->assertArrayHasKey('fiabilite_reelle', $p['backtest']);
        $this->assertArrayHasKey('palier', $m['notes']); // fiabilité palier ≠ garantie de précision
        $this->assertArrayHasKey('produits_testes', $m['backtest_global']);
    }

    public function test_apprend_sur_la_quantite_commandee_pas_livree_regle_4_5(): void
    {
        // On force un produit local dont commandé >> livré, sur assez de mois.
        $randa = Societe::where('code', 'RAN')->first();
        foreach (range(1, 6) as $mois) {
            $c = Commande::factory()->for($randa)->create([
                'numero' => "CMTESTRAN{$mois}", 'scope' => 'local',
                'date_commande' => sprintf('2026-%02d-10', $mois),
                'etat' => 'cloturee',
            ]);
            CommandeLigne::create([
                'commande_id' => $c->id, 'article' => 'ARTX', 'designation' => 'Produit test',
                'unite_vente' => 'KG',
                'qte_commandee_raw' => 10000, 'qte_livree_raw' => 1000, 'qte_facturee_raw' => 1000,
                'qte_commandee_kg' => 10000, 'qte_livree_kg' => 1000, 'qte_facturee_kg' => 1000,
                'poids_unitaire_kg' => 1, 'poids_origine' => 'kg_neutre',
                'compte_volume' => true, 'volume_fiable' => true,
                'is_eligible' => true, 'is_satisfied' => false, 'line_hash' => "h{$mois}",
            ]);
        }

        $m = (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->admin()));
        $artx = collect($m['produits'])->firstWhere('article', 'ARTX');

        // la prévision doit être de l'ordre du COMMANDÉ (~10 000), pas du livré (~1 000)
        $this->assertGreaterThan(5000, $artx['prevision']['quantite_prevue_kg']);
    }

    public function test_produits_sans_poids_listes_a_part_sans_prevision(): void
    {
        // La fixture a l'article 900115 (SAC « PRODUIT VRAC SANS INDICATION ») => poids non déterminé.
        $m = (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->admin()));

        $articlesSansPoids = array_column($m['sans_poids'], 'article');
        $this->assertContains('900115', $articlesSansPoids);
        $this->assertNotContains('900115', array_column($m['produits'], 'article'));
    }

    public function test_snapshots_ecrits_par_societe_a_l_import(): void
    {
        // le writer tourne déjà dans le setUp via l'import
        $this->assertGreaterThan(0, ForecastSnapshot::count());
        $this->assertGreaterThan(0, ForecastSnapshot::whereNotNull('societe_id')->count());

        $snap = ForecastSnapshot::first();
        $this->assertNotNull($snap->mois_cible);
        $this->assertNotNull($snap->genere_le);
        $this->assertNull($snap->qte_a_produire_kg); // réservé V3

        // ré-import => remplacement, pas de doublon (article, societe, mois)
        $avant = ForecastSnapshot::count();
        (new ForecastSnapshotWriter)->run();
        $this->assertSame($avant, ForecastSnapshot::count());
    }

    public function test_export_exclu_des_previsions_regle_2_2(): void
    {
        $m = (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->admin()));

        // l'article de la ligne export de la fixture (311331) ne doit pas apparaître
        $this->assertNotContains('311331', array_column($m['produits'], 'article'));
    }
}
