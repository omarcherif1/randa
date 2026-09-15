<?php

namespace Tests\Feature\Access;

use App\Domain\Dashboard\CommandesTableQuery;
use App\Domain\Dashboard\DashboardFilter;
use App\Domain\Dashboard\DashboardMetrics;
use App\Domain\Dashboard\FilterOptions;
use App\Domain\Delivery\DeliveryMetrics;
use App\Domain\Export\ExportMetrics;
use App\Domain\Forecast\ForecastFilter;
use App\Domain\Forecast\ForecastMetrics;
use App\Domain\Import\ImportManager;
use App\Domain\Stock\StockMetrics;
use App\Models\Commande;
use App\Models\Societe;
use App\Models\User;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Section 9 étape 10 / défaut 3.1 — vérification systématique et testée du
 * respect du rattachement société sur TOUTES les surfaces.
 *
 * La fixture porte des commandes Randa et SMT (aucune TF). Un utilisateur
 * rattaché à SMT ne doit jamais voir une donnée Randa, où que ce soit.
 */
class SocieteScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $userSmt;

    /** @var list<string> numéros de commande Randa (invisibles pour un user SMT) */
    private array $numerosRanda;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->fakeMlServiceDown(); // ce test couvre le scoping société, pas le ML (règle 4.5)
        $this->seed([
            SocieteSeeder::class, FamilleSeeder::class, ClientCategorySeeder::class,
            SettingSeeder::class, ClassificationKeywordSeeder::class, PermissionTemplateSeeder::class,
        ]);
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');

        $ran = Societe::where('code', 'RAN')->first();
        $this->numerosRanda = Commande::where('societe_id', $ran->id)->pluck('numero')->all();

        $this->userSmt = User::factory()->create();
        $this->userSmt->societes()->attach(Societe::where('code', 'SMT')->first());
        foreach (['dashboard', 'livraisons', 'stock', 'previsions', 'export'] as $p) {
            $this->userSmt->pagePermissions()->create(['page_key' => $p]);
        }
    }

    private function assertSansRanda(mixed $charge, string $surface): void
    {
        $json = json_encode($charge, JSON_UNESCAPED_UNICODE);
        foreach ($this->numerosRanda as $numero) {
            $this->assertStringNotContainsString(
                $numero,
                $json,
                "Donnée Randa visible pour un utilisateur SMT dans « {$surface} » : {$numero}",
            );
        }
        // le mot « AMBIGU » n'apparaît que chez un client Randa de la fixture
        $this->assertStringNotContainsString('AMBIGU', $json, "Client Randa visible dans « {$surface} »");
    }

    public function test_prerequis_la_fixture_a_bien_du_randa_et_du_smt(): void
    {
        $this->assertNotEmpty($this->numerosRanda);
        $this->assertGreaterThan(0, Commande::where('societe_id', Societe::where('code', 'SMT')->value('id'))->count());
    }

    public function test_tableau_de_bord_scope_a_la_societe(): void
    {
        $filter = new DashboardFilter(user: $this->userSmt);
        $m = (new DashboardMetrics)->forFilter($filter);

        $this->assertSame(
            Commande::local()->where('societe_id', Societe::where('code', 'SMT')->value('id'))->count(),
            $m['kpi']['commandes']['total'],
        );
        $this->assertSansRanda($m, 'DashboardMetrics');
        $this->assertSansRanda((new FilterOptions)->forUser($this->userSmt), 'FilterOptions');
        $this->assertSansRanda(
            (new CommandesTableQuery)->paginate(Request::create('/dashboard', 'GET'), $filter),
            'CommandesTableQuery',
        );
    }

    public function test_bloc_satisfaction_montre_les_trois_societes_mais_sans_donnees_randa_chiffrees(): void
    {
        // Règle 2.3 : les 3 sociétés sont toujours listées côte à côte…
        $m = (new DashboardMetrics)->forFilter(new DashboardFilter(user: $this->userSmt));
        $codes = array_column($m['satisfaction']['par_societe'], 'code');
        $this->assertEqualsCanonicalizing(['RAN', 'SMT', 'TF'], $codes);

        // … mais un user SMT ne peut pas voir les volumes Randa : la ligne RAN est à zéro.
        $ran = collect($m['satisfaction']['par_societe'])->firstWhere('code', 'RAN');
        $this->assertSame(0, $ran['commandes']);
    }

    public function test_livraisons_scope_a_la_societe(): void
    {
        $m = (new DeliveryMetrics)->forUser($this->userSmt);
        $this->assertSame(
            Commande::local()->where('societe_id', Societe::where('code', 'SMT')->value('id'))->count(),
            $m['total'],
        );
        $this->assertSansRanda($m, 'DeliveryMetrics');
    }

    public function test_stock_et_previsions_scopes_a_la_societe(): void
    {
        $this->assertSansRanda((new StockMetrics)->forUser($this->userSmt), 'StockMetrics');
        $this->assertSansRanda(
            (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->userSmt)),
            'ForecastMetrics',
        );
    }

    public function test_export_scope_a_la_societe(): void
    {
        // la seule commande export de la fixture est Randa
        $m = (new ExportMetrics)->forUser($this->userSmt);
        $this->assertSame(0, $m['kpi']['commandes']);
        $this->assertSansRanda($m, 'ExportMetrics');
    }

    public function test_un_utilisateur_sans_societe_ne_voit_rien(): void
    {
        $orphelin = User::factory()->create();
        $orphelin->pagePermissions()->create(['page_key' => 'dashboard']);

        $m = (new DashboardMetrics)->forFilter(new DashboardFilter(user: $orphelin));
        $this->assertSame(0, $m['kpi']['commandes']['total']);
    }

    public function test_les_pages_http_restent_scopees(): void
    {
        $reponse = $this->actingAs($this->userSmt)->get('/dashboard');
        $reponse->assertOk();
        foreach ($this->numerosRanda as $numero) {
            $reponse->assertDontSee($numero, false);
        }
    }
}
