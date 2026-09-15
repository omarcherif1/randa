<?php

namespace Tests\Feature\Polish;

use App\Domain\Import\ImportManager;
use App\Domain\Reporting\IndicatorGlossary;
use App\Domain\Reporting\WeeklyReport;
use App\Models\Setting;
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
 * Étape 9 (passe 1) — polissage : pédagogie intégrée (glossaire + page
 * « Comment lire ces chiffres »), fraîcheur partagée, rapport hebdomadaire.
 */
class PolishTest extends TestCase
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
    }

    public function test_le_glossaire_reflete_les_seuils_reglables_en_vigueur(): void
    {
        Setting::put('seuil_retard_jours', 4, audit: false);

        $g = IndicatorGlossary::all();

        $this->assertArrayHasKey('satisfaction', $g);
        $this->assertArrayHasKey('retard', $g);
        $this->assertSame('4 jour(s)', $g['retard']['seuil']);
        $this->assertSame('2.3', $g['satisfaction']['regle']);
        $this->assertNotEmpty($g['satisfaction']['exclusions']);
    }

    public function test_glossaire_et_fraicheur_sont_partages_a_inertia(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('aide.indicateurs'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Aide/Indicateurs')
                ->has('glossaire.satisfaction')
                ->has('fraicheur') // null tant qu'aucun import, mais la clé existe
            );
    }

    public function test_la_page_aide_est_ouverte_a_tout_compte_connecte(): void
    {
        $u = User::factory()->create();
        $u->pagePermissions()->create(['page_key' => 'livraisons']);

        $this->actingAs($u)->get(route('aide.indicateurs'))->assertOk();
    }

    public function test_rapport_hebdomadaire_compose_deux_fenetres_de_7_jours(): void
    {
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');

        $r = (new WeeklyReport)->pour(User::factory()->admin()->create());

        $this->assertArrayHasKey('periode', $r);
        $this->assertArrayHasKey('periode_precedente', $r);
        $this->assertArrayHasKey('kpi', $r);
        $this->assertArrayHasKey('kpi_precedent', $r);
        $this->assertCount(3, $r['satisfaction'], 'toujours les 3 sociétés (règle 2.3)');
        $this->assertArrayHasKey('taux_precedent', $r['satisfaction'][0]);
    }

    public function test_rapport_hebdomadaire_ancre_sur_les_donnees_pas_sur_l_horloge(): void
    {
        // La fixture est datée de mai 2026 — largement hors des « 7 derniers
        // jours calendaires » au moment où ce test tourne. Si la fenêtre
        // était ancrée sur l'horloge système (défaut précédent), ce rapport
        // afficherait 0 commande partout alors que des données existent bel
        // et bien. Elle doit s'ancrer sur la dernière date de commande réelle.
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');

        $r = (new WeeklyReport)->pour(User::factory()->admin()->create());

        $this->assertGreaterThan(0, $r['kpi']['commandes']['total'], 'la semaine écoulée doit contenir de vraies commandes');
        $this->assertNotSame(
            now()->toDateString(),
            $r['periode']['au'],
            'ancrée sur la dernière commande réelle, pas sur la date du jour',
        );
    }

    public function test_rapport_hebdomadaire_html_est_servi(): void
    {
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard.rapport.hebdo', ['html' => 1]))
            ->assertOk()
            ->assertSee('Rapport hebdomadaire', false);
    }

    public function test_rapport_hebdomadaire_reserve_a_qui_a_le_tableau_de_bord(): void
    {
        $u = User::factory()->create();
        $u->pagePermissions()->create(['page_key' => 'livraisons']);

        $this->actingAs($u)->get(route('dashboard.rapport.hebdo'))->assertForbidden();
    }

    public function test_le_theme_sombre_est_applique_avant_le_rendu(): void
    {
        // Le HTML de base pose la classe `dark` sans attendre le JS (pas de flash).
        $this->get(route('login'))
            ->assertOk()
            ->assertSee("localStorage.getItem('theme')", false)
            ->assertSee('prefers-color-scheme: dark', false);
    }
}
