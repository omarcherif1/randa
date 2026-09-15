<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Dashboard\CommandesTableQuery;
use App\Domain\Dashboard\DashboardFilter;
use App\Domain\Dashboard\DashboardMetrics;
use App\Domain\Dashboard\FilterOptions;
use App\Domain\Import\ImportManager;
use App\Domain\Reporting\MetricsCache;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\Societe;
use App\Models\User;
use Carbon\CarbonImmutable;
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
 * Étape 4 — tableau de bord principal (section 4.2). Local uniquement (2.2),
 * satisfaction par société jamais moyennée (2.3), filtrage société réel (3.1),
 * agrégats en cache invalidés à l'import (exigence 7).
 */
class DashboardTest extends TestCase
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

    public function test_metrics_local_uniquement_regle_2_2(): void
    {
        $m = (new DashboardMetrics)->forFilter(new DashboardFilter(user: $this->admin()));

        // la fixture a 1 commande export (EXPRAN01260138, 300 t) : elle ne doit PAS peser.
        $this->assertLessThan(150, $m['kpi']['tonnes_distribuees'], 'les 300 t export ne comptent pas');
        foreach ($m['classements']['top_produits_commandes'] as $p) {
            $this->assertNotSame('311331', $p['article']); // article de la ligne export
        }
    }

    public function test_satisfaction_toujours_trois_societes_meme_avec_filtre_societe_regle_2_3(): void
    {
        $filtre = new DashboardFilter(user: $this->admin(), societes: ['RAN']);
        $m = (new DashboardMetrics)->forFilter($filtre);

        $codes = array_column($m['satisfaction']['par_societe'], 'code');
        $this->assertEqualsCanonicalizing(['RAN', 'SMT', 'TF'], $codes);
        // SMT reste renseigné bien que le filtre société soit "RAN"
        $smt = collect($m['satisfaction']['par_societe'])->firstWhere('code', 'SMT');
        $this->assertGreaterThan(0, $smt['commandes']);
    }

    public function test_filtrage_societe_reel_pour_un_non_admin_regle_3_1(): void
    {
        $randa = Societe::where('code', 'RAN')->first();
        $userRanda = User::factory()->create();
        $userRanda->societes()->attach($randa);

        $m = (new DashboardMetrics)->forFilter(new DashboardFilter(user: $userRanda));

        // KPI (respecte le rattachement) : aucune tonne SMT
        $codesRepartition = array_column($m['repartitions']['par_societe'], 'code');
        $this->assertSame(['RAN'], $codesRepartition);
    }

    public function test_le_cache_est_invalide_a_chaque_import(): void
    {
        $v1 = MetricsCache::version();
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');
        $this->assertGreaterThan($v1, MetricsCache::version());
    }

    public function test_la_page_s_affiche_pour_qui_a_le_droit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertForbidden();

        $user->pagePermissions()->create(['page_key' => 'dashboard']);
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Dashboard')
                ->has('metrics.kpi')
                ->has('metrics.satisfaction.par_societe', 3)
                ->missing('commandes')); // chargé en différé
    }

    public function test_la_table_commandes_pagine_trie_et_cherche_cote_serveur(): void
    {
        $q = new CommandesTableQuery;

        $r = Request::create('/dashboard', 'GET', ['tri' => 'numero', 'sens' => 'asc']);
        $res = $q->paginate($r, new DashboardFilter(user: $this->admin()));

        $this->assertLessThanOrEqual(25, count($res['lignes']['data']));
        $this->assertSame('numero', $res['meta']['tri']);
        // aucune commande export dans la table (règle 2.2)
        foreach ($res['lignes']['data'] as $ligne) {
            $this->assertStringStartsWith('CM', $ligne['numero']);
        }

        $recherche = Request::create('/dashboard', 'GET', ['q' => 'SOCO CAB']);
        $filtre = $q->paginate($recherche, new DashboardFilter(user: $this->admin()));
        $this->assertNotEmpty($filtre['lignes']['data']);
    }

    public function test_export_csv_ne_contient_que_du_local(): void
    {
        $res = $this->actingAs($this->admin())->get(route('dashboard.export.csv'));
        $res->assertOk();
        $contenu = $res->streamedContent();

        $this->assertStringContainsString('CMRAN012605170', $contenu);
        $this->assertStringNotContainsString('EXPRAN01260138', $contenu);
    }

    public function test_filtre_famille_combine_avec_periode(): void
    {
        $m = (new DashboardMetrics)->forFilter(new DashboardFilter(
            user: $this->admin(),
            familles: ['106'], // PATES NORMALES
            from: CarbonImmutable::parse('2026-05-01'),
            to: CarbonImmutable::parse('2026-05-31'),
        ));

        foreach ($m['commande_vs_livre'] as $row) {
            $this->assertContains($row['label'], ['PAT NORMALES']);
        }
    }

    public function test_les_options_type_client_n_offrent_que_les_categories_reellement_utilisees(): void
    {
        // Règle 2.10/2.7 — le référentiel `client_categories` a 21 catégories
        // partagées par tout le groupe (même en local, même sondage sur les
        // données réelles) ; en proposer une SANS client local la sélectionne
        // dans le filtre pour un résultat vide garanti, perçu comme "le filtre
        // ne marche pas". Même principe que familles/articles (déjà filtrés
        // à l'usage réel) — étendu ici au type de client.
        $options = (new FilterOptions)->forUser($this->admin());
        $codes = array_column($options['client_categories'], 'code');

        $this->assertNotContains(
            '101',
            $codes,
            "« Eleveurs » n'a aucun client dans la fixture locale, ne doit pas apparaître",
        );

        $codesReellementUtilises = Client::query()
            ->whereHas('commandes', fn ($q) => $q->where('scope', 'local'))
            ->whereNotNull('client_category_id')
            ->with('category:id,code')
            ->get()
            ->pluck('category.code')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($codesReellementUtilises, $codes);
    }

    public function test_filtres_societe_et_produit_acceptent_plusieurs_valeurs(): void
    {
        // Règle 2.10 — société / produit / type client / type famille sont tous
        // des filtres à sélection MULTIPLE (décision produit).
        $ran = Societe::where('code', 'RAN')->first();
        $smt = Societe::where('code', 'SMT')->first();

        $filtre = new DashboardFilter(user: $this->admin(), societes: [$ran->code, $smt->code]);
        $m = (new DashboardMetrics)->forFilter($filtre);
        $this->assertSame(
            $m['kpi']['commandes']['total'],
            (new DashboardMetrics)->forFilter(new DashboardFilter(user: $this->admin()))['kpi']['commandes']['total'],
            'RAN + SMT = la totalité des commandes locales de la fixture',
        );

        $seulRan = (new DashboardMetrics)->forFilter(new DashboardFilter(user: $this->admin(), societes: [$ran->code]));
        $this->assertLessThan($m['kpi']['commandes']['total'], $seulRan['kpi']['commandes']['total']);

        // deux articles précis via CommandesTableQuery : au moins les commandes
        // portant l'un OU l'autre, jamais plus que le total local.
        $q = new CommandesTableQuery;
        $toutes = $q->paginate(Request::create('/dashboard', 'GET'), new DashboardFilter(user: $this->admin()));
        $res = $q->paginate(
            Request::create('/dashboard', 'GET'),
            new DashboardFilter(user: $this->admin(), articles: ['113231', '900112']),
        );
        $this->assertGreaterThan(0, $res['meta']['total']);
        $this->assertLessThan($toutes['meta']['total'], $res['meta']['total']);
    }

    public function test_le_taux_par_societe_est_le_ratio_de_commandes_satisfaites_regle_2_3(): void
    {
        // Décision produit (2026-09-11, méthode finale) : le taux d'une société
        // est le nombre de commandes SATISFAITES / le nombre total de commandes.
        // Une commande est satisfaite si sa quantité livrée (kg) couvre sa
        // quantité commandée (kg) — la sur-livraison compte comme satisfaite,
        // seul un manquant (commandée > livrée) rend la commande insatisfaite.
        $ran = Societe::where('code', 'RAN')->first();

        $satisfaite = Commande::factory()->create([
            'societe_id' => $ran->id, 'scope' => 'local', 'etat' => 'cloturee',
        ]);
        CommandeLigne::create([
            'commande_id' => $satisfaite->id, 'article' => 'ARTSAT',
            'qte_commandee_raw' => 100, 'qte_livree_raw' => 100,
            'qte_commandee_kg' => 100, 'qte_livree_kg' => 100,
            'compte_volume' => true, 'volume_fiable' => true,
            'is_eligible' => true, 'is_satisfied' => true, 'line_hash' => 'sat-1',
        ]);

        $surLivree = Commande::factory()->create([
            'societe_id' => $ran->id, 'scope' => 'local', 'etat' => 'cloturee',
        ]);
        CommandeLigne::create([
            'commande_id' => $surLivree->id, 'article' => 'ARTSUR',
            'qte_commandee_raw' => 50, 'qte_livree_raw' => 60,
            'qte_commandee_kg' => 50, 'qte_livree_kg' => 60,
            'compte_volume' => true, 'volume_fiable' => true,
            'is_eligible' => true, 'is_satisfied' => true, 'line_hash' => 'sur-1',
        ]);

        $nonSatisfaite = Commande::factory()->create([
            'societe_id' => $ran->id, 'scope' => 'local', 'etat' => 'cloturee',
        ]);
        CommandeLigne::create([
            'commande_id' => $nonSatisfaite->id, 'article' => 'ARTNON',
            'qte_commandee_raw' => 100, 'qte_livree_raw' => 80,
            'qte_commandee_kg' => 100, 'qte_livree_kg' => 80,
            'compte_volume' => true, 'volume_fiable' => true,
            'is_eligible' => true, 'is_satisfied' => false, 'line_hash' => 'non-1',
        ]);

        // Filtre sur les seuls articles synthétiques ci-dessus, pour isoler ce
        // scénario des commandes RAN déjà présentes dans la fixture importée
        // par setUp() — sansSociete() ignore le filtre société mais respecte
        // toujours les autres filtres (produit, famille, période...).
        $filtre = new DashboardFilter(user: $this->admin(), articles: ['ARTSAT', 'ARTSUR', 'ARTNON']);
        $m = (new DashboardMetrics)->forFilter($filtre);
        $ranStats = collect($m['satisfaction']['par_societe'])->firstWhere('code', 'RAN');

        $this->assertSame(3, $ranStats['commandes']);
        $this->assertSame(2, $ranStats['satisfaites'], 'la commande sur-livrée compte comme satisfaite');
        $this->assertSame(round(2 / 3, 4), $ranStats['taux']);
    }
}
