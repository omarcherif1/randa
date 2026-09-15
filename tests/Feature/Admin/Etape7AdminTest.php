<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\AuditAction;
use App\Domain\Import\ImportManager;
use App\Domain\Reporting\MetricsCache;
use App\Models\Anomalie;
use App\Models\ArticleFamily;
use App\Models\ArticleUnitWeight;
use App\Models\ClassificationKeyword;
use App\Models\ClientCategory;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\Famille;
use App\Models\ImportBatch;
use App\Models\User;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Étape 7 — Administration / Paramètres complets : anomalies (règle 2.9),
 * corrections de données par article (règles 2.8 / 2.6), mots-clés (règle 2.7).
 * Toute action est réservée aux admins et journalisée (3.1 / 3.2).
 */
class Etape7AdminTest extends TestCase
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
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'unified_sample.csv');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // --- Accès -------------------------------------------------------------

    public function test_pages_admin_interdites_aux_non_admins(): void
    {
        $user = User::factory()->create();
        $user->pagePermissions()->create(['page_key' => 'dashboard']);

        $this->actingAs($user)->get(route('admin.anomalies'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.corrections'))->assertForbidden();
        $this->actingAs($user)->post(route('parametres.keywords.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('admin.import'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.import.store'), [])->assertForbidden();
    }

    public function test_admin_ouvre_les_pages_anomalies_et_corrections(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.anomalies'))->assertOk();
        $this->actingAs($admin)->get(route('admin.corrections', ['onglet' => 'poids']))->assertOk();
        $this->actingAs($admin)->get(route('admin.corrections', ['onglet' => 'familles']))->assertOk();
        $this->actingAs($admin)->get(route('admin.import'))->assertOk();
    }

    // --- Import web (section 4.7 — réutilise ImportManager tel quel) -------

    public function test_import_web_traite_le_fichier_et_journalise(): void
    {
        $admin = $this->admin();
        $avant = Commande::count();

        $contenu = file_get_contents(base_path('tests/Fixtures/unified_sample.csv'));
        $fichier = UploadedFile::fake()->createWithContent('unified_sample.csv', $contenu);

        $response = $this->actingAs($admin)->post(route('admin.import.store'), [
            'fichier' => $fichier,
            'type' => 'unified',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(session('status'), 'un message de confirmation doit être présent');
        $this->assertSame($avant, Commande::count(), 're-import du même fichier : remplace, n\'ajoute pas (règle 4.7)');
    }

    public function test_import_web_refuse_un_fichier_qui_n_est_pas_un_csv(): void
    {
        $admin = $this->admin();
        $fichier = UploadedFile::fake()->create('rapport.pdf', 10, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('admin.import.store'), ['fichier' => $fichier, 'type' => 'unified'])
            ->assertSessionHasErrors('fichier');
    }

    public function test_suppression_import_interdite_aux_non_admins(): void
    {
        $batch = ImportBatch::first();
        $user = User::factory()->create();
        $user->pagePermissions()->create(['page_key' => 'dashboard']);

        $this->actingAs($user)->delete(route('admin.import.destroy', $batch))->assertForbidden();
        $this->assertNotNull(ImportBatch::find($batch->id));
    }

    public function test_suppression_import_retire_le_journal_sans_toucher_aux_commandes(): void
    {
        $admin = $this->admin();
        $batch = ImportBatch::first();
        $commandesAvant = Commande::count();
        $commandesDuBatch = Commande::where('import_batch_id', $batch->id)->count();
        $this->assertGreaterThan(0, $commandesDuBatch, 'la fixture doit avoir rattaché des commandes à ce batch');

        $response = $this->actingAs($admin)->delete(route('admin.import.destroy', $batch));

        $response->assertRedirect();
        $this->assertNull(ImportBatch::find($batch->id), 'le journal est bien retiré');
        $this->assertSame($commandesAvant, Commande::count(), 'aucune commande supprimée');
        $this->assertSame(
            0,
            Commande::where('import_batch_id', $batch->id)->count(),
            'la référence au batch supprimé est mise à NULL (nullOnDelete), pas la commande',
        );
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::DONNEES_SUPPRIMEES]);
    }

    // --- Anomalies (règle 2.9) -------------------------------------------

    public function test_valider_une_valeur_aberrante_reintegre_la_ligne(): void
    {
        $admin = $this->admin();
        $anomalie = Anomalie::where('type', 'outlier_quantite')->where('article', '900111')->firstOrFail();

        $ligne = CommandeLigne::where('article', '900111')->firstOrFail();
        $this->assertTrue($ligne->is_outlier);

        $versionAvant = MetricsCache::version();

        $this->actingAs($admin)
            ->post(route('admin.anomalies.resolve', $anomalie), ['decision' => 'validee', 'note' => 'Vérifié'])
            ->assertRedirect();

        $ligne->refresh();
        $this->assertFalse($ligne->is_outlier);
        $this->assertTrue($ligne->is_eligible);
        $this->assertTrue($ligne->compte_volume);

        $this->assertFalse(Commande::where('numero', 'CMSMT012608966')->value('has_outlier'));
        $this->assertSame('validee', $anomalie->fresh()->statut);
        $this->assertNotSame($versionAvant, MetricsCache::version(), 'le cache est invalidé');
        $this->assertDatabaseHas('audit_logs', ['action' => 'anomalie.tranchee', 'user_id' => $admin->id]);
    }

    public function test_exclure_une_valeur_aberrante_laisse_la_ligne_hors_indicateurs(): void
    {
        $admin = $this->admin();
        $anomalie = Anomalie::where('type', 'outlier_quantite')->where('article', '900111')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.anomalies.resolve', $anomalie), ['decision' => 'exclue'])
            ->assertRedirect();

        $this->assertTrue(CommandeLigne::where('article', '900111')->value('is_outlier'));
        $this->assertSame('exclue', $anomalie->fresh()->statut);
    }

    public function test_exclure_apres_avoir_valide_ressort_bien_la_ligne(): void
    {
        // L'admin change d'avis : trancher() doit rester symétrique dans les deux
        // sens, y compris pour réexclure une ligne déjà réintégrée par erreur.
        $admin = $this->admin();
        $anomalie = Anomalie::where('type', 'outlier_quantite')->where('article', '900111')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.anomalies.resolve', $anomalie), ['decision' => 'validee']);
        $this->assertFalse(CommandeLigne::where('article', '900111')->value('is_outlier'));

        $this->actingAs($admin)
            ->post(route('admin.anomalies.resolve', $anomalie), ['decision' => 'exclue'])
            ->assertRedirect();

        $ligne = CommandeLigne::where('article', '900111')->first();
        $this->assertTrue($ligne->is_outlier);
        $this->assertFalse($ligne->is_eligible);
        $this->assertFalse($ligne->compte_volume);
        $this->assertSame('exclue', $anomalie->fresh()->statut);
    }

    public function test_decision_refuse_une_valeur_invalide(): void
    {
        $admin = $this->admin();
        $anomalie = Anomalie::where('type', 'outlier_quantite')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.anomalies.resolve', $anomalie), ['decision' => 'peut_etre'])
            ->assertSessionHasErrors('decision');
    }

    // --- Corrections de poids (règle 2.8) --------------------------------

    public function test_correction_de_poids_recalcule_les_tonnages(): void
    {
        $admin = $this->admin();
        $this->assertNull(ArticleUnitWeight::where('article', '900115')->value('poids_unitaire_kg'));

        $this->actingAs($admin)
            ->put(route('admin.corrections.poids'), ['article' => '900115', 'poids_kg' => 50])
            ->assertRedirect();

        $ref = ArticleUnitWeight::where('article', '900115')->firstOrFail();
        $this->assertSame(50.0, (float) $ref->poids_unitaire_kg);
        $this->assertSame('correction_manuelle', $ref->origine);

        $ligne = CommandeLigne::where('article', '900115')->firstOrFail();
        $this->assertSame(
            round((float) $ligne->qte_commandee_raw * 50, 3),
            round((float) $ligne->qte_commandee_kg, 3),
        );
        $this->assertTrue($ligne->compte_volume, 'la ligne compte maintenant dans les volumes');
        $this->assertDatabaseHas('audit_logs', ['action' => 'parametre.modifie', 'user_id' => $admin->id]);
    }

    public function test_correction_de_poids_refuse_un_poids_negatif(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.corrections.poids'), ['article' => '900115', 'poids_kg' => -3])
            ->assertSessionHasErrors('poids_kg');
    }

    // --- Corrections de famille (règle 2.6) ------------------------------

    public function test_correction_de_famille_tranche_un_article_ambigu(): void
    {
        $admin = $this->admin();
        $ambigu = ArticleFamily::where('article', '514127')->firstOrFail();
        $this->assertSame('ambigu', $ambigu->statut);
        $this->assertNull($ambigu->famille_code);

        $this->actingAs($admin)
            ->put(route('admin.corrections.famille'), ['article' => '514127', 'famille_code' => '130'])
            ->assertRedirect();

        $ambigu->refresh();
        $this->assertSame('correction_manuelle', $ambigu->statut);
        $this->assertSame('130', $ambigu->famille_code);

        $familleId = Famille::where('code', '130')->value('id');
        $this->assertSame(
            [$familleId],
            CommandeLigne::where('article', '514127')->distinct()->pluck('famille_id')->all(),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'parametre.modifie']);
    }

    // --- Mots-clés de classification (règle 2.7) -------------------------

    public function test_admin_ajoute_modifie_supprime_un_mot_cle(): void
    {
        $admin = $this->admin();
        $categorie = ClientCategory::orderBy('id')->first();

        $this->actingAs($admin)->post(route('parametres.keywords.store'), [
            'categorie_code' => $categorie->code,
            'keyword' => 'Carrefour Market',
            'priorite' => 70,
        ])->assertRedirect();

        $kw = ClassificationKeyword::where('keyword', 'CARREFOUR MARKET')->firstOrFail();
        $this->assertSame(70, $kw->priorite);
        $this->assertDatabaseHas('audit_logs', ['action' => 'classification.mots_cles_modifies']);

        $this->actingAs($admin)->put(route('parametres.keywords.update', $kw), [
            'priorite' => 40, 'actif' => false,
        ])->assertRedirect();
        $this->assertSame(40, $kw->fresh()->priorite);
        $this->assertFalse($kw->fresh()->actif);

        $this->actingAs($admin)->delete(route('parametres.keywords.destroy', $kw))->assertRedirect();
        $this->assertModelMissing($kw);
    }

    public function test_reclasser_les_clients_non_tranches_est_journalise(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('parametres.keywords.reclassify'))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'classification.clients_reclasses',
            'user_id' => $admin->id,
        ]);
    }
}
