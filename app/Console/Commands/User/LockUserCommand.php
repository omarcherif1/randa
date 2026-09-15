<?php

namespace App\Console\Commands\User;

use App\Domain\Access\AccountService;
use App\Models\User;
use Illuminate\Console\Command;

class LockUserCommand extends Command
{
    protected $signature = 'randa:user:lock {email} {--reason=} {--unlock : Déverrouiller au lieu de verrouiller}';

    protected $description = 'Verrouille (ou déverrouille) un compte — un compte verrouillé n\'a plus aucun accès';

    public function handle(AccountService $accounts): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('Utilisateur introuvable.');

            return self::FAILURE;
        }

        if ($this->option('unlock')) {
            $accounts->deverrouiller($user);
            $this->info("Compte {$user->email} déverrouillé.");
        } else {
            $accounts->verrouiller($user, $this->option('reason') ?: null);
            $this->info("Compte {$user->email} verrouillé — sessions ouvertes invalidées à la prochaine requête.");
        }

        return self::SUCCESS;
    }
}
