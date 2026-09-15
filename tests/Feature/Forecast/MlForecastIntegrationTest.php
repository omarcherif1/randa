<?php

namespace Tests\Feature\Forecast;

use App\Domain\Forecast\ForecastFilter;
use App\Domain\Forecast\ForecastMetrics;
use App\Domain\Import\ImportManager;
use App\Domain\Reporting\MetricsCache;
use App\Models\User;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Règle 4.5 (v2) — prévision par modèle ML (service Python externe), avec
 * repli automatique sur le calcul PHP quand le service est indisponible ou
 * pas encore entraîné. La page ne doit JAMAIS casser si le service ML est
 * éteint — c'est le cœur de ce que ces tests vérifient.
 */
class MlForecastIntegrationTest extends TestCase
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

    public function test_repli_sur_le_calcul_si_le_service_ml_est_injoignable(): void
    {
        Http::fake(['127.0.0.1:8001/*' => fn () => throw new ConnectionException('refusé')]);

        $m = (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->admin()));

        $this->assertSame('calcul', $m['methode']);
        $this->assertNull($m['modele_ml']);
        $this->assertNotEmpty($m['produits'], 'la page doit continuer de fonctionner sans le service ML');
    }

    public function test_repli_sur_le_calcul_si_le_modele_n_est_pas_encore_entraine(): void
    {
        Http::fake(['127.0.0.1:8001/status' => Http::response(['entraine' => false])]);

        $m = (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->admin()));

        $this->assertSame('calcul', $m['methode']);
    }

    public function test_utilise_le_ml_quand_le_service_repond_des_predictions(): void
    {
        // Article réel de la fixture (SAC 50 kg, avec poids déterminé — voir
        // UnifiedFileImporterTest::test_poids_unitaire_memorise_par_article).
        Http::fake([
            '127.0.0.1:8001/status' => Http::response([
                'entraine' => true, 'entraine_le' => '2026-09-13T10:00:00Z',
                'n_echantillons' => 1200, 'n_produits' => 150,
                'mae_kg' => 120.5, 'wape' => 0.12, 'r2' => 0.8,
            ]),
            '127.0.0.1:8001/predict' => Http::response([
                'produits' => [
                    ['article' => '900112', 'designation' => 'Test ML', 'previsions' => [
                        ['mois' => '2026-09', 'quantite_prevue_kg' => 4242.0],
                    ]],
                ],
                'modele_entraine_le' => '2026-09-13T10:00:00Z',
                'n_echantillons_entrainement' => 1200,
            ]),
        ]);

        $m = (new ForecastMetrics)->forFilter(new ForecastFilter(user: $this->admin()));

        $this->assertSame('ml', $m['methode']);
        $this->assertSame(1200, $m['modele_ml']['n_echantillons']);

        $article = collect($m['produits'])->firstWhere('article', '900112');
        $this->assertNotNull($article, 'l\'article 900112 doit être présent dans les prévisions');
        $this->assertEquals(4242.0, $article['prevision']['quantite_prevue_kg']);
        $this->assertSame('ml', $article['prevision']['methode']);
    }

    // --- Réentraînement (bouton Administration, page Prévisions) ----------

    public function test_reentrainement_interdit_aux_non_admins(): void
    {
        $user = User::factory()->create();
        $user->pagePermissions()->create(['page_key' => 'previsions']);

        $this->actingAs($user)
            ->post(route('previsions.reentrainer'))
            ->assertForbidden();
    }

    public function test_reentrainement_reussi_journalise_et_invalide_le_cache(): void
    {
        Http::fake(['127.0.0.1:8001/train' => Http::response([
            'entraine_le' => '2026-09-13T10:00:00Z',
            'n_echantillons' => 1200, 'n_produits' => 150,
            'mae_kg' => 120.5, 'wape' => 0.12, 'r2' => 0.8,
        ])]);
        $v1 = MetricsCache::version();

        $response = $this->actingAs($this->admin())->post(route('previsions.reentrainer'));

        $response->assertRedirect();
        $this->assertNotNull(session('status'));
        $this->assertGreaterThan($v1, MetricsCache::version(), 'le cache doit être invalidé après un réentraînement');
    }

    public function test_reentrainement_echoue_proprement_si_le_service_est_injoignable(): void
    {
        Http::fake(['127.0.0.1:8001/train' => fn () => throw new ConnectionException('refusé')]);

        $response = $this->actingAs($this->admin())->post(route('previsions.reentrainer'));

        $response->assertRedirect();
        $this->assertNotNull(session('erreur'));
    }
}
