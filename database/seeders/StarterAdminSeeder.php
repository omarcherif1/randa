<?php

namespace Database\Seeders;

use App\Models\Societe;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Compte d'amorçage (environnement local uniquement, via DatabaseSeeder).
 *
 * Règle 3.2 — le mot de passe provisoire est connu, mais `must_change_password`
 * force son changement à la première connexion : aucun mot de passe par défaut
 * ne reste jamais en place.
 */
class StarterAdminSeeder extends Seeder
{
    public const EMAIL = 'admin@randa.local';

    public const MOT_DE_PASSE_PROVISOIRE = 'Admin-Provisoire-2026';

    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Administrateur',
                'password' => bcrypt(self::MOT_DE_PASSE_PROVISOIRE),
                'is_admin' => true,
                'must_change_password' => true,
            ],
        );

        $admin->societes()->sync(Societe::pluck('id'));
    }
}
