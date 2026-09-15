<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Section 3.2 — durcissement de la connexion + règle 3.1 (verrouillage,
 * changement de mot de passe forcé, accès page par page).
 */
class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pas_d_inscription_publique(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    public function test_compte_verrouille_ne_peut_pas_se_connecter(): void
    {
        $user = User::factory()->locked()->create(['password' => Hash::make('password')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('login_attempts', ['email' => $user->email, 'successful' => false, 'motif_echec' => 'compte_verrouille']);
    }

    public function test_verrouillage_temporaire_apres_5_echecs(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'faux'.$i]);
        }

        $user->refresh();
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());

        // même le bon mot de passe est refusé pendant le verrou
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_connexion_reussie_est_journalisee_et_reinitialise_les_compteurs(): void
    {
        $user = User::factory()->admin()->create([
            'password' => Hash::make('password'),
            'failed_login_attempts' => 3,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
        $user->refresh();
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertNotNull($user->last_login_at);
        $this->assertDatabaseHas('login_attempts', ['user_id' => $user->id, 'successful' => true]);
    }

    public function test_verrouillage_en_pleine_session_coupe_l_acces(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);

        $this->get('/dashboard')->assertOk();

        $user->forceFill(['locked_at' => now()])->save();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_changement_de_mot_de_passe_force_bloque_la_navigation(): void
    {
        $user = User::factory()->admin()->mustChangePassword()->create();
        $this->actingAs($user);

        $this->get('/dashboard')->assertRedirect(route('password.forced'));
        $this->get(route('password.forced'))->assertOk();

        $this->put(route('password.forced.update'), [
            'current_password' => 'password',
            'password' => 'Nouveau-MDP-2026',
            'password_confirmation' => 'Nouveau-MDP-2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/dashboard')->assertOk();
    }

    public function test_mot_de_passe_force_refuse_un_mot_de_passe_trop_faible(): void
    {
        $user = User::factory()->admin()->mustChangePassword()->create();

        $this->actingAs($user)->put(route('password.forced.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_middleware_page_refuse_l_acces_non_accorde(): void
    {
        $user = User::factory()->create(); // non-admin, aucune page
        $this->actingAs($user);

        $this->get('/dashboard')->assertForbidden();

        $user->pagePermissions()->create(['page_key' => 'dashboard']);
        $this->get('/dashboard')->assertOk();
    }
}
