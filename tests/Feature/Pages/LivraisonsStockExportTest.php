<?php

namespace Tests\Feature\Pages;

use App\Domain\Delivery\DeliveryMetrics;
use App\Domain\Export\ExportMetrics;
use App\Domain\Export\ExportTableQuery;
use App\Domain\Import\ImportManager;
use App\Domain\Stock\StockMetrics;
use App\Models\Commande;
use App\Models\Setting;
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
 * Étape 5 — pages Livraisons (4.3), Stock (4.4), Export (4.6).
 * Chacune hérite des règles déjà validées, sans dupliquer la logique.
 */
class LivraisonsStockExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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

    // --- Livraisons -------------------------------------------------------

    public function test_livraisons_retard_calcule_sur_la_date_de_facture_regle_2_4(): void
    {
        // Décision de l'encadreur : la date de FACTURE sert de proxy accepté.
        // Le module n'est plus bloqué globalement ; seules les commandes sans
        // date de facture (CMRAN012605206, CMRAN012605207) restent "non calculables".
        $m = (new DeliveryMetrics)->forUser($this->admin());

        $this->assertTrue($m['retard_exploitable']);
        $this->assertSame(2, $m['retards']['donnee_indisponible']);
        $this->assertSame(0, $m['retards']['en_retard'], 'aucune commande de la fixture ne dépasse le seuil');
        $this->assertSame($m['total'] - 2, $m['retards']['a_temps']);
    }

    public function test_livraisons_etat_de_cycle_et_incertitude(): void
    {
        $m = (new DeliveryMetrics)->forUser($this->admin());

        $this->assertArrayHasKey('cloturee', $m['par_etat']);
        $this->assertArrayHasKey('reservee', $m['par_etat']);
        $this->assertGreaterThan(0, $m['par_etat']['cloturee']);
    }

    public function test_livraisons_local_uniquement(): void
    {
        // la commande export de la fixture (EXPRAN01260138) ne doit pas être comptée
        $m = (new DeliveryMetrics)->forUser($this->admin());
        $this->assertSame(Commande::local()->count(), $m['total']);
        $this->assertLessThan(Commande::count(), $m['total']);
    }

    public function test_concentration_par_ville_couvre_toutes_les_commandes_locales(): void
    {
        $villes = (new DeliveryMetrics)->forUser($this->admin())['concentration_villes'];

        $this->assertNotEmpty($villes);
        $this->assertSame($villes, collect($villes)->sortByDesc('commandes')->values()->all(), 'triées par commandes');
        $this->assertSame(Commande::local()->count(), array_sum(array_column($villes, 'commandes')));
        $cles = array_map(fn ($v) => str_replace(' ', '', \App\Support\Text::normalize($v['ville'])), $villes);
        $this->assertSame(count($cles), count(array_unique($cles)), 'variantes d\'écriture regroupées');
    }

    public function test_livraisons_page_gardee_par_permission(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/livraisons')->assertForbidden();
        $u->pagePermissions()->create(['page_key' => 'livraisons']);
        $this->actingAs($u)->get('/livraisons')->assertOk();
    }

    // --- Stock -----------------------------------------------------------

    public function test_stock_methode_active_affichee_regle_2_5(): void
    {
        $m = (new StockMetrics)->forUser($this->admin());

        $this->assertSame('B', $m['methode']); // pas de fichier stock => méthode B
        $this->assertStringContainsString('commandes ouvertes', $m['methode_libelle']);
        $this->assertSame(
            $m['compte']['rupture'] + $m['compte']['alerte'] + $m['compte']['normal'] + $m['compte']['indetermine'],
            $m['total_produits'],
        );
    }

    public function test_stock_page_gardee_par_permission(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/stock')->assertForbidden();
        $u->pagePermissions()->create(['page_key' => 'stock']);
        $this->actingAs($u)->get('/stock')->assertOk();
    }

    // --- Export --------------------------------------------------------

    public function test_export_totalement_separe_du_local_regle_2_2(): void
    {
        $m = (new ExportMetrics)->forUser($this->admin());

        // la fixture a 1 seule commande export
        $this->assertSame(1, $m['kpi']['commandes']);

        $table = (new ExportTableQuery)->paginate(Request::create('/export', 'GET'), $this->admin());
        foreach ($table['lignes']['data'] as $ligne) {
            $this->assertStringStartsWith('EXP', $ligne['numero']);
        }
    }

    public function test_export_satisfaction_par_societe_jamais_melangee(): void
    {
        $m = (new ExportMetrics)->forUser($this->admin());

        $codes = array_column($m['satisfaction_par_societe'], 'code');
        $this->assertEqualsCanonicalizing(['RAN', 'SMT', 'TF'], $codes);
    }

    public function test_export_aucune_regle_locale(): void
    {
        $m = (new ExportMetrics)->forUser($this->admin());
        $this->assertStringContainsString('Aucune règle locale', $m['perimetre']);
    }

    public function test_export_respecte_le_rattachement_societe(): void
    {
        $smt = Societe::where('code', 'SMT')->first();
        $userSmt = User::factory()->create();
        $userSmt->societes()->attach($smt);

        $m = (new ExportMetrics)->forUser($userSmt);
        // la seule commande export de la fixture est EXPRAN (Randa) => invisible pour un user SMT
        $this->assertSame(0, $m['kpi']['commandes']);
    }

    public function test_export_page_gardee_par_permission(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/export')->assertForbidden();
        $u->pagePermissions()->create(['page_key' => 'export']);
        $this->actingAs($u)->get('/export')->assertOk();
    }

    public function test_methode_stock_a_utilisee_si_un_fichier_stock_est_importe(): void
    {
        Setting::put('stock_methode_active', 'A', audit: false);
        app(ImportManager::class)->import(
            base_path('tests/Fixtures/stock_sample.csv'), 'stock.csv', ImportManager::TYPE_STOCK,
        );

        $m = (new StockMetrics)->forUser($this->admin());
        $this->assertSame('A', $m['methode']);
    }
}
