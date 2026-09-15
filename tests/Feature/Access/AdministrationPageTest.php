<?php

namespace Tests\Feature\Access;

use App\Models\Setting;
use App\Models\Societe;
use App\Models\User;
use Database\Seeders\PermissionTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SocieteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 4.8 / 4.9 — pages Administration et Paramètres réservées aux admins.
 */
class AdministrationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SocieteSeeder::class, SettingSeeder::class, PermissionTemplateSeeder::class]);
    }

    public function test_non_admin_ne_peut_pas_ouvrir_l_administration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    public function test_admin_ouvre_l_administration(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.index'))
            ->assertOk();
    }

    public function test_admin_cree_un_compte_via_le_formulaire(): void
    {
        $admin = User::factory()->admin()->create();
        $societeIds = Societe::pluck('id')->all();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Nadia',
            'email' => 'nadia@randa.local',
            'is_admin' => false,
            'societes' => $societeIds,
            'pages' => ['dashboard', 'livraisons'],
        ])->assertRedirect();

        $user = User::where('email', 'nadia@randa.local')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->pagePermissions()->where('page_key', 'dashboard')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'compte.cree']);
    }

    public function test_admin_ne_peut_pas_verrouiller_son_propre_compte(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.lock', $admin))
            ->assertStatus(422);
    }

    public function test_modification_d_un_seuil_est_auditee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('parametres.update'), [
            'key' => 'seuil_retard_jours',
            'value' => 3,
        ])->assertRedirect();

        $this->assertSame(3, Setting::get('seuil_retard_jours'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'parametre.modifie']);
    }

    public function test_parametres_interdit_aux_non_admins(): void
    {
        $user = User::factory()->create();
        $user->pagePermissions()->create(['page_key' => 'dashboard']);

        $this->actingAs($user)->get(route('parametres.index'))->assertForbidden();
    }
}
