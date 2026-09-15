<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Database\Seeders\SocieteSeeder;
use Database\Seeders\StarterAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Règle 3.2 — même le compte d'amorçage seedé en base est soumis au
 * changement de mot de passe forcé : jamais de mot de passe par défaut qui reste.
 */
class SeededAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_admin_seede_doit_changer_son_mot_de_passe_a_la_premiere_connexion(): void
    {
        $this->seed([SocieteSeeder::class, StarterAdminSeeder::class]);

        $admin = User::where('email', StarterAdminSeeder::EMAIL)->firstOrFail();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->must_change_password, 'le compte seedé doit forcer le changement');
        $this->assertTrue(Hash::check(StarterAdminSeeder::MOT_DE_PASSE_PROVISOIRE, $admin->password));
        $this->assertNotEmpty($admin->societeIds());

        // À la connexion : redirigé vers l'écran de changement, pas vers l'application.
        $this->post('/login', [
            'email' => StarterAdminSeeder::EMAIL,
            'password' => StarterAdminSeeder::MOT_DE_PASSE_PROVISOIRE,
        ])->assertRedirect(route('password.forced'));

        // La navigation reste bloquée tant que ce n'est pas fait.
        $this->get(route('admin.index'))->assertRedirect(route('password.forced'));

        // Le mot de passe provisoire lui-même serait refusé comme nouveau mot de passe
        // s'il figurait dans la liste des mots de passe évidents ; ici on vérifie
        // simplement que le changement débloque l'accès.
        $this->put(route('password.forced.update'), [
            'current_password' => StarterAdminSeeder::MOT_DE_PASSE_PROVISOIRE,
            'password' => 'Cle-De-Voute-4712',
            'password_confirmation' => 'Cle-De-Voute-4712',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($admin->fresh()->must_change_password);
        $this->get(route('admin.index'))->assertOk();
    }
}
