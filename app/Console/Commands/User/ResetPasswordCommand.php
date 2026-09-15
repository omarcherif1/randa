<?php

namespace App\Console\Commands\User;

use App\Domain\Access\AccountService;
use App\Models\User;
use Illuminate\Console\Command;

class ResetPasswordCommand extends Command
{
    protected $signature = 'randa:user:password {email}';

    protected $description = 'Réinitialise le mot de passe d\'un compte (nouveau provisoire + changement forcé)';

    public function handle(AccountService $accounts): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('Utilisateur introuvable.');

            return self::FAILURE;
        }

        $provisoire = $accounts->reinitialiserMotDePasse($user);

        $this->info("Mot de passe de {$user->email} réinitialisé.");
        $this->warn("  Nouveau mot de passe provisoire : {$provisoire}");

        return self::SUCCESS;
    }
}
