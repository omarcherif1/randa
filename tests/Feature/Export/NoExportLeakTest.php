<?php

namespace Tests\Feature\Export;

use App\Domain\Dashboard\CommandesTableQuery;
use App\Domain\Dashboard\DashboardFilter;
use App\Domain\Dashboard\DashboardMetrics;
use App\Domain\Dashboard\FilterOptions;
use App\Domain\Delivery\DeliveryMetrics;
use App\Domain\Delivery\DeliveryTableQuery;
use App\Domain\Forecast\ForecastFilter;
use App\Domain\Forecast\ForecastMetrics;
use App\Domain\Import\ImportManager;
use App\Domain\Notification\NotificationCenter;
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
 * Règle 2.2 / section 9 étape 10 — « défaut à vérifier explicitement lors des
 * tests : qu'aucune fuite de données export ne subsiste nulle part dans les vues
 * locales ».
 *
 * La fixture contient UNE commande export (EXPRAN01260138) dont le client, le
 * produit et la ville n'apparaissent QUE là. On balaie toutes les surfaces
 * locales et on vérifie qu'aucun de ces marqueurs n'y apparaît.
 */
class NoExportLeakTest extends TestCase
{
    use RefreshDatabase;

    /** Marqueurs présents uniquement dans la commande export de la fixture. */
    private const MARQUEURS = ['EXPRAN01260138', '900999Z', 'PASTA REGALE', 'GENOVA', '311331'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->fakeMlServiceDown(); // ce test couvre la fuite export/local, pas le ML (règle 4.5)
        $this->seed([
            SocieteSeeder::class, FamilleSeeder::class, ClientCategorySeeder::class,
            SettingSeeder::class, ClassificationKeywordSeeder::class, PermissionTemplateSeeder::class,
        ]);
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');

        $this->admin = User::factory()->admin()->create();
    }

    private function assertSansExport(mixed $charge, string $surface): void
    {
        $json = json_encode($charge, JSON_UNESCAPED_UNICODE);
        foreach (self::MARQUEURS as $marqueur) {
            $this->assertStringNotContainsString(
                $marqueur,
                $json,
                "Fuite export détectée dans « {$surface} » : {$marqueur}",
            );
        }
    }

    public function test_la_fixture_contient_bien_une_commande_export_isolee(): void
    {
        $this->assertSame(1, Commande::export()->count());
        $this->assertSame('EXPRAN01260138', Commande::export()->value('numero'));
        // sans quoi le test n'aurait rien à prouver
        $this->assertGreaterThan(1, Commande::local()->count());
    }

    public function test_aucune_fuite_dans_le_tableau_de_bord(): void
    {
        $filter = new DashboardFilter(user: $this->admin);

        $this->assertSansExport((new DashboardMetrics)->forFilter($filter), 'DashboardMetrics');
        $this->assertSansExport((new FilterOptions)->forUser($this->admin), 'FilterOptions (listes déroulantes)');
        $this->assertSansExport(
            (new CommandesTableQuery)->paginate(Request::create('/dashboard', 'GET'), $filter),
            'CommandesTableQuery',
        );
    }

    public function test_aucune_fuite_dans_livraisons(): void
    {
        $this->assertSansExport((new DeliveryMetrics)->forUser($this->admin), 'DeliveryMetrics');
        $this->assertSansExport(
            (new DeliveryTableQuery)->paginate(Request::create('/livraisons', 'GET'), $this->admin),
            'DeliveryTableQuery',
        );
    }

    public function test_aucune_fuite_dans_stock_et_previsions(): void
    {
        $this->assertSansExport((new StockMetrics)->forUser($this->admin), 'StockMetrics');
        $this->assertSansExport(
            (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->admin)),
            'ForecastMetrics',
        );
    }

    public function test_aucune_fuite_dans_les_notifications(): void
    {
        $this->assertSansExport(NotificationCenter::fromConfig()->pour($this->admin), 'NotificationCenter');
    }

    public function test_aucune_fuite_dans_les_exports_du_tableau_de_bord(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('dashboard.export.csv'));
        $csv->assertOk();
        $this->assertSansExport($csv->streamedContent(), 'Export CSV');

        $pdf = $this->actingAs($this->admin)->get(route('dashboard.rapport', ['html' => 1]));
        $pdf->assertOk();
        $this->assertSansExport($pdf->getContent(), 'Rapport PDF (HTML)');

        $hebdo = $this->actingAs($this->admin)->get(route('dashboard.rapport.hebdo', ['html' => 1]));
        $hebdo->assertOk();
        $this->assertSansExport($hebdo->getContent(), 'Rapport hebdomadaire (HTML)');
    }

    public function test_aucune_fuite_pour_un_utilisateur_rattache_a_randa(): void
    {
        // La commande export est une commande Randa : un utilisateur Randa ne
        // doit pas la voir non plus dans ses vues locales.
        $user = User::factory()->create();
        $user->societes()->attach(Societe::where('code', 'RAN')->first());

        $filter = new DashboardFilter(user: $user);
        $this->assertSansExport((new DashboardMetrics)->forFilter($filter), 'DashboardMetrics (user Randa)');
        $this->assertSansExport((new FilterOptions)->forUser($user), 'FilterOptions (user Randa)');
        $this->assertSansExport((new DeliveryMetrics)->forUser($user), 'DeliveryMetrics (user Randa)');
    }
}
