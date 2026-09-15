<?php

namespace Tests\Feature\Reporting;

use App\Domain\Dashboard\DashboardFilter;
use App\Domain\Dashboard\DashboardMetrics;
use App\Domain\Delivery\DeliveryMetrics;
use App\Domain\Export\ExportMetrics;
use App\Domain\Import\ImportManager;
use App\Domain\Reporting\MetricsCache;
use App\Domain\Stock\StockMetrics;
use App\Models\User;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exigence 7 — UN SEUL mécanisme de cache pour toute l'application.
 * Agrégats jamais recalculés à chaque affichage ; invalidés uniquement à
 * l'import (version) ou au changement de filtre (clé).
 */
class MetricsCacheTest extends TestCase
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

    public function test_toutes_les_pages_utilisent_le_meme_mecanisme(): void
    {
        $admin = $this->admin();

        // 1er appel = calcul (des requêtes SQL sont émises)
        $requetes1 = $this->compterRequetes(fn () => (new DashboardMetrics)->forFilter(new DashboardFilter(user: $admin)));
        $requetesLivr1 = $this->compterRequetes(fn () => (new DeliveryMetrics)->forUser($admin));
        $requetesStock1 = $this->compterRequetes(fn () => (new StockMetrics)->forUser($admin));
        $requetesExport1 = $this->compterRequetes(fn () => (new ExportMetrics)->forUser($admin));

        // 2e appel identique = servi depuis le cache (quasi aucune requête)
        $this->assertLessThan($requetes1, $this->compterRequetes(fn () => (new DashboardMetrics)->forFilter(new DashboardFilter(user: $admin))));
        $this->assertLessThan($requetesLivr1, $this->compterRequetes(fn () => (new DeliveryMetrics)->forUser($admin)));
        $this->assertLessThan($requetesStock1, $this->compterRequetes(fn () => (new StockMetrics)->forUser($admin)));
        $this->assertLessThan($requetesExport1, $this->compterRequetes(fn () => (new ExportMetrics)->forUser($admin)));
    }

    public function test_un_nouvel_import_invalide_le_cache_de_toutes_les_pages(): void
    {
        $admin = $this->admin();
        (new DashboardMetrics)->forFilter(new DashboardFilter(user: $admin));
        (new DeliveryMetrics)->forUser($admin);

        $v = MetricsCache::version();
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');
        $this->assertSame($v + 1, MetricsCache::version());

        // après import : recalcul (le cache de version v est devenu inatteignable)
        $requetes = $this->compterRequetes(fn () => (new DeliveryMetrics)->forUser($admin));
        $this->assertGreaterThan(0, $requetes);
    }

    public function test_un_changement_de_filtre_ne_recalcule_que_le_sous_ensemble_concerne(): void
    {
        $admin = $this->admin();

        (new DashboardMetrics)->forFilter(new DashboardFilter(user: $admin)); // sans filtre => en cache

        // filtre différent => nouvelle clé => calcul de CE sous-ensemble seulement
        $avecFiltre = $this->compterRequetes(
            fn () => (new DashboardMetrics)->forFilter(new DashboardFilter(user: $admin, societes: ['RAN'])),
        );
        $this->assertGreaterThan(0, $avecFiltre);

        // le résultat sans filtre reste servi depuis le cache (pas recalculé)
        $sansFiltre = $this->compterRequetes(
            fn () => (new DashboardMetrics)->forFilter(new DashboardFilter(user: $admin)),
        );
        $this->assertLessThan($avecFiltre, $sansFiltre);
    }

    private function compterRequetes(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }
}
