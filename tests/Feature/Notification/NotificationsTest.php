<?php

namespace Tests\Feature\Notification;

use App\Domain\Notification\NotificationCenter;
use App\Domain\Reporting\MetricsCache;
use App\Models\Anomalie;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\ImportBatch;
use App\Models\Societe;
use App\Models\User;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Étape 8 / section 6 — centre de notifications : alertes automatiques, visibles
 * uniquement selon les droits, extensible sans changer la structure.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed([
            SocieteSeeder::class, FamilleSeeder::class, ClientCategorySeeder::class,
            SettingSeeder::class, PermissionTemplateSeeder::class,
        ]);
    }

    private function centre(): NotificationCenter
    {
        return NotificationCenter::fromConfig();
    }

    /** Crée une commande locale ouverte avec un article sous-livré (méthode B → rupture). */
    private function ligneRupture(string $article, float $cmd, float $liv): void
    {
        $ran = Societe::where('code', 'RAN')->first();
        $commande = Commande::factory()->for($ran)->create(['etat' => 'reservee']);
        CommandeLigne::create([
            'commande_id' => $commande->id,
            'article' => $article,
            'designation' => "Produit {$article}",
            'unite_vente' => 'KG',
            'qte_commandee_raw' => $cmd,
            'qte_livree_raw' => $liv,
            'qte_facturee_raw' => $liv,
            'compte_volume' => true,
            'is_eligible' => true,
            'line_hash' => md5($article),
        ]);
    }

    // --- Règle retards (section 6 #1) ------------------------------------

    public function test_aucune_alerte_de_retard_pour_une_commande_pas_encore_facturee(): void
    {
        // Proxy accepté (date de facture) : pas encore facturée => non calculable
        // pour CETTE commande, jamais un faux retard, même vieille de 30 jours.
        $ran = Societe::where('code', 'RAN')->first();
        Commande::factory()->for($ran)->create([
            'etat' => 'saisie',
            'date_commande' => now()->subDays(30),
            'date_facture' => null,
        ]);

        $alertes = $this->centre()->pour(User::factory()->admin()->create())['alertes'];

        $this->assertSame([], array_values(array_filter(
            $alertes, fn ($a) => $a['type'] === 'retard_commande',
        )));
    }

    public function test_retards_top_5_couverture_pire_en_premier(): void
    {
        $ran = Societe::where('code', 'RAN')->first();

        foreach ([10, 4, 8, 6, 20, 3, 12] as $i => $ecart) {
            Commande::factory()->for($ran)->create([
                'etat' => 'cloturee',
                'date_commande' => now()->subDays(40),
                'date_facture' => now()->subDays(40 - $ecart),
            ]);
        }

        $retards = array_values(array_filter(
            $this->centre()->pour(User::factory()->admin()->create())['alertes'],
            fn ($a) => $a['type'] === 'retard_commande',
        ));

        $this->assertCount(5, $retards, 'plafonné à 5 (section 6)');
        $this->assertStringContainsString('18 j', $retards[0]['titre']); // écart 20 - seuil 2
    }

    // --- Règle ruptures (section 6 #2) ---------------------------------

    public function test_ruptures_stock_listees_couverture_la_plus_faible_en_premier(): void
    {
        $this->ligneRupture('RUP-A', 100, 30); // 30 %
        $this->ligneRupture('RUP-B', 100, 5);  // 5 %  -> en premier
        $this->ligneRupture('OK', 100, 90);    // 90 % -> pas une rupture

        $ruptures = array_values(array_filter(
            $this->centre()->pour(User::factory()->admin()->create())['alertes'],
            fn ($a) => $a['type'] === 'rupture_stock',
        ));

        $this->assertCount(2, $ruptures);
        $this->assertSame('rupture:RUP-B', $ruptures[0]['reference']);
        $this->assertSame('rupture:RUP-A', $ruptures[1]['reference']);
    }

    // --- Visibilité selon les droits (règle 3.1) ----------------------

    public function test_une_alerte_n_est_visible_que_par_qui_a_acces_a_la_page_concernee(): void
    {
        $this->ligneRupture('RUP-A', 100, 10);
        Anomalie::create([
            'type' => 'famille_ambigue', 'severity' => 'warning',
            'resume' => 'x', 'article' => '123', 'statut' => Anomalie::STATUT_A_VERIFIER,
        ]);

        $stockUser = User::factory()->create();
        $stockUser->pagePermissions()->create(['page_key' => 'stock']);
        $stockUser->societes()->attach(Societe::where('code', 'RAN')->first());

        $types = array_column($this->centre()->pour($stockUser)['alertes'], 'type');
        $this->assertContains('rupture_stock', $types);
        $this->assertNotContains('anomalies_a_verifier', $types, 'réservé à l\'Administration');

        $admin = User::factory()->admin()->create();
        $typesAdmin = array_column($this->centre()->pour($admin)['alertes'], 'type');
        $this->assertContains('anomalies_a_verifier', $typesAdmin);
    }

    public function test_le_rattachement_societe_filtre_les_ruptures(): void
    {
        $this->ligneRupture('RUP-RAN', 100, 10); // société RAN

        $userSmt = User::factory()->create();
        $userSmt->pagePermissions()->create(['page_key' => 'stock']);
        $userSmt->societes()->attach(Societe::where('code', 'SMT')->first());

        $types = array_column($this->centre()->pour($userSmt)['alertes'], 'type');
        $this->assertNotContains('rupture_stock', $types);
    }

    // --- Extensions (échec import / anomalies) ------------------------

    public function test_echec_du_dernier_import_alerte_les_administrateurs(): void
    {
        ImportBatch::create([
            'type' => 'unified', 'original_filename' => 'ko.csv', 'stored_path' => 'x',
            'status' => 'failed', 'error_message' => 'Fichier illisible.',
        ]);

        $alertes = $this->centre()->pour(User::factory()->admin()->create())['alertes'];
        $echec = array_values(array_filter($alertes, fn ($a) => $a['type'] === 'echec_import'));

        $this->assertCount(1, $echec);
        $this->assertSame('critique', $echec[0]['severity']);
    }

    public function test_anomalies_a_verifier_disparait_quand_tout_est_tranche(): void
    {
        $anomalie = Anomalie::create([
            'type' => 'famille_ambigue', 'severity' => 'warning',
            'resume' => 'x', 'article' => '123', 'statut' => Anomalie::STATUT_A_VERIFIER,
        ]);
        $admin = User::factory()->admin()->create();

        $this->assertContains(
            'anomalies_a_verifier',
            array_column($this->centre()->pour($admin)['alertes'], 'type'),
        );

        $anomalie->update(['statut' => Anomalie::STATUT_EXCLUE]);
        MetricsCache::bump(); // comme le fait AnomalyResolver

        $this->assertNotContains(
            'anomalies_a_verifier',
            array_column($this->centre()->pour($admin)['alertes'], 'type'),
        );
    }

    // --- Cache partagé (exigence 7) -----------------------------------

    public function test_le_resultat_est_mis_en_cache_et_invalide_a_l_import(): void
    {
        $admin = User::factory()->admin()->create();
        $this->assertSame(0, $this->centre()->pour($admin)['count']);

        // ajout hors flux normal : sans bump, l'ancien résultat caché est renvoyé
        ImportBatch::create([
            'type' => 'unified', 'original_filename' => 'ko.csv', 'stored_path' => 'x',
            'status' => 'failed', 'error_message' => 'boom',
        ]);
        $this->assertSame(0, $this->centre()->pour($admin)['count'], 'servi depuis le cache');

        MetricsCache::bump();
        $this->assertSame(1, $this->centre()->pour($admin)['count']);
    }

    // --- Page ---------------------------------------------------------

    public function test_la_page_notifications_est_accessible_a_tout_compte_connecte(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('notifications'))
            ->assertOk();
    }

    public function test_les_alertes_sont_partagees_a_toutes_les_pages_inertia(): void
    {
        $this->ligneRupture('RUP-A', 100, 10);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('notifications'))->assertInertia(
            fn ($page) => $page->has('notifications.alertes')->where('notifications.count', fn ($c) => $c >= 1),
        );
    }
}
