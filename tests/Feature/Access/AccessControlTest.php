<?php

namespace Tests\Feature\Access;

use App\Domain\Access\AccessControl;
use App\Domain\Access\AccountService;
use App\Domain\Access\SocieteScope;
use App\Models\AuditLog;
use App\Models\Commande;
use App\Models\PermissionTemplate;
use App\Models\Societe;
use App\Models\User;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règle 3.1 — accès page par page / personne par personne, rattachement société.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private AccessControl $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SocieteSeeder::class, PermissionTemplateSeeder::class]);
        $this->access = new AccessControl;
    }

    public function test_admin_voit_toutes_les_pages_dont_administration_et_parametres(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($this->access->canAccessPage($admin, 'dashboard'));
        $this->assertTrue($this->access->canAccessPage($admin, 'administration'));
        $this->assertTrue($this->access->canAccessPage($admin, 'parametres'));
    }

    public function test_non_admin_n_a_que_les_pages_explicitement_accordees(): void
    {
        $user = User::factory()->create();
        $user->pagePermissions()->create(['page_key' => 'livraisons']);

        $this->assertTrue($this->access->canAccessPage($user, 'livraisons'));
        $this->assertFalse($this->access->canAccessPage($user, 'dashboard'));
        // jamais d'accès aux pages admin, même si on tente de l'accorder
        $user->pagePermissions()->create(['page_key' => 'administration']);
        $this->assertFalse($this->access->canAccessPage($user, 'administration'));
    }

    public function test_compte_verrouille_perd_tout_acces(): void
    {
        $user = User::factory()->admin()->locked()->create();

        $this->assertFalse($this->access->canAccessPage($user, 'dashboard'));
        $this->assertSame([], $this->access->accessiblePages($user));
    }

    public function test_le_rattachement_societe_filtre_reellement_les_donnees(): void
    {
        $randa = Societe::where('code', 'RAN')->first();
        $smt = Societe::where('code', 'SMT')->first();
        Commande::factory()->for($randa)->create(['numero' => 'CMRAN1', 'scope' => 'local']);
        Commande::factory()->for($smt)->create(['numero' => 'CMSMT1', 'scope' => 'local']);

        $userRanda = User::factory()->create();
        $userRanda->societes()->attach($randa);

        $scope = new SocieteScope;
        $visibles = $scope->apply(Commande::query(), $userRanda)->pluck('numero')->all();

        $this->assertSame(['CMRAN1'], $visibles);
    }

    public function test_personne_sans_societe_ne_voit_rien(): void
    {
        Societe::where('code', 'RAN')->first();
        Commande::factory()->create(['numero' => 'CMX', 'scope' => 'local']);
        $user = User::factory()->create(); // aucune société

        $this->assertSame(0, (new SocieteScope)->apply(Commande::query(), $user)->count());
    }

    public function test_admin_voit_toutes_les_societes(): void
    {
        Commande::factory()->count(3)->create(['scope' => 'local']);
        $admin = User::factory()->admin()->create();

        $this->assertSame(3, (new SocieteScope)->apply(Commande::query(), $admin)->count());
    }

    public function test_account_service_cree_un_compte_avec_changement_force_et_audit(): void
    {
        $accounts = new AccountService;
        $ids = Societe::whereIn('code', ['RAN', 'SMT'])->pluck('id')->all();

        [$user, $provisoire] = $accounts->creer('Bob', 'bob@randa.local', false, $ids, ['dashboard']);

        $this->assertTrue($user->must_change_password);
        $this->assertFalse($user->is_admin);
        $this->assertEqualsCanonicalizing($ids, $user->societeIds());
        $this->assertTrue($user->pagePermissions()->where('page_key', 'dashboard')->exists());
        $this->assertGreaterThanOrEqual(10, strlen($provisoire));
        $this->assertDatabaseHas('audit_logs', ['action' => 'compte.cree']);
    }

    public function test_appliquer_un_modele_prerempli_les_pages_puis_reste_modifiable(): void
    {
        $accounts = new AccountService;
        [$user] = $accounts->creer('Dir', 'dir@randa.local', false, Societe::pluck('id')->all());

        $accounts->appliquerModele($user, PermissionTemplate::where('code', 'direction')->first());
        $this->assertTrue($user->pagePermissions()->where('page_key', 'export')->exists());

        // affinage individuel : on retire une page
        $accounts->definirPages($user, ['dashboard']);
        $this->assertFalse($user->fresh()->pagePermissions()->where('page_key', 'export')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'droits.modifies']);
    }

    public function test_verrouillage_et_deverrouillage_sont_audites(): void
    {
        $accounts = new AccountService;
        $user = User::factory()->create();

        $accounts->verrouiller($user, 'départ');
        $this->assertTrue($user->fresh()->isLocked());

        $accounts->deverrouiller($user);
        $this->assertFalse($user->fresh()->isLocked());

        $this->assertSame(1, AuditLog::where('action', 'compte.verrouille')->count());
        $this->assertSame(1, AuditLog::where('action', 'compte.deverrouille')->count());
    }
}
