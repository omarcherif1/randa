<?php

namespace Tests\Feature\Backup;

use App\Domain\Backup\DatabaseBackup;
use App\Domain\Backup\DatabaseRestore;
use App\Domain\Import\ImportManager;
use App\Models\Anomalie;
use App\Models\AuditLog;
use App\Models\Commande;
use App\Models\CommandeLigne;
use App\Models\Setting;
use Database\Seeders\ClassificationKeywordSeeder;
use Database\Seeders\ClientCategorySeeder;
use Database\Seeders\FamilleSeeder;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Section 3.2 / étape 10 — la sauvegarde est réellement restaurable : on
 * sauvegarde, on casse les données, on restaure, on vérifie que tout est revenu
 * à l'identique (base + fichiers sources importés).
 */
class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $racine;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->racine = storage_path('framework/testing/backups_'.uniqid());
        $this->seed([
            SocieteSeeder::class, FamilleSeeder::class, ClientCategorySeeder::class,
            SettingSeeder::class, ClassificationKeywordSeeder::class, PermissionTemplateSeeder::class,
        ]);
        app(ImportManager::class)->import(base_path('tests/Fixtures/unified_sample.csv'), 'sample.csv');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->racine);
        parent::tearDown();
    }

    public function test_sauvegarde_puis_restauration_rend_les_donnees_a_l_identique(): void
    {
        $commandes = Commande::count();
        $lignes = CommandeLigne::count();
        $anomalies = Anomalie::count();
        $audits = AuditLog::count();
        $qteRef = Commande::where('numero', 'CMRAN012605170')->value('qte_livree_kg');
        $fichiersImportes = count(Storage::disk('local')->allFiles('imports'));
        $this->assertGreaterThan(0, $commandes);
        $this->assertGreaterThan(0, $fichiersImportes);

        // 1. Sauvegarde
        $backup = (new DatabaseBackup($this->racine))->executer('test');
        $this->assertFileExists($backup['chemin'].'/manifest.json');
        $this->assertFileExists($backup['chemin'].'/tables/commandes.jsonl');
        $this->assertSame($commandes, $backup['manifest']['tables']['commandes']);
        $this->assertSame($fichiersImportes, $backup['manifest']['fichiers_imports']);

        // 2. On casse tout
        CommandeLigne::query()->delete();
        Commande::query()->delete();
        Anomalie::query()->delete();
        Setting::query()->where('key', 'seuil_retard_jours')->update(['value' => ['v' => 999]]);
        Storage::disk('local')->deleteDirectory('imports');
        $this->assertSame(0, Commande::count());
        $this->assertEmpty(Storage::disk('local')->allFiles('imports'));

        // 3. Restauration
        $manifest = (new DatabaseRestore)->executer($backup['chemin']);
        $this->assertSame('test', $manifest['label']);

        // 4. Vérifications
        $this->assertSame($commandes, Commande::count());
        $this->assertSame($lignes, CommandeLigne::count());
        $this->assertSame($anomalies, Anomalie::count());
        $this->assertSame($audits, AuditLog::count());
        $this->assertEquals($qteRef, Commande::where('numero', 'CMRAN012605170')->value('qte_livree_kg'));
        $this->assertSame(2, Setting::get('seuil_retard_jours'), 'le paramètre modifié est revenu à sa valeur sauvegardée');
        $this->assertCount($fichiersImportes, Storage::disk('local')->allFiles('imports'));
    }

    public function test_la_purge_conserve_le_nombre_configure_de_sauvegardes(): void
    {
        config()->set('backup.conserver', 2);

        for ($i = 0; $i < 4; $i++) {
            (new DatabaseBackup($this->racine))->executer('n'.$i);
        }

        $this->assertCount(2, File::directories($this->racine));
    }

    public function test_les_commandes_artisan_fonctionnent(): void
    {
        config()->set('backup.racine', $this->racine);

        $this->artisan('randa:backup', ['--label' => 'cli'])->assertSuccessful();

        Commande::query()->delete();

        $this->artisan('randa:restore', ['--force' => true])->assertSuccessful();
        $this->assertGreaterThan(0, Commande::count());
    }

    public function test_restauration_refuse_un_dossier_sans_manifeste(): void
    {
        File::ensureDirectoryExists($this->racine.'/vide');

        $this->expectException(\RuntimeException::class);
        (new DatabaseRestore)->executer($this->racine.'/vide');
    }
}
