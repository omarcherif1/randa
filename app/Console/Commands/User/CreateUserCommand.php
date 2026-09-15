<?php

namespace App\Console\Commands\User;

use App\Domain\Access\AccountService;
use App\Models\Societe;
use Illuminate\Console\Command;

class CreateUserCommand extends Command
{
    protected $signature = 'randa:user:create
        {email}
        {name}
        {--admin : Compte administrateur (Administration + Paramètres)}
        {--societe=* : Codes société rattachés (RAN, SMT, TF). Défaut : toutes}
        {--page=* : Pages accordées (voir randa:user:access)}';

    protected $description = 'Crée un compte utilisateur avec mot de passe provisoire';

    public function handle(AccountService $accounts): int
    {
        $codes = $this->option('societe') ?: Societe::pluck('code')->all();
        $societeIds = Societe::whereIn('code', $codes)->pluck('id')->all();

        if ($societeIds === []) {
            $this->error('Aucune société valide. Codes connus : '.Societe::pluck('code')->implode(', '));

            return self::FAILURE;
        }

        [$user, $provisoire] = $accounts->creer(
            nom: $this->argument('name'),
            email: $this->argument('email'),
            admin: (bool) $this->option('admin'),
            societeIds: $societeIds,
            pageKeys: $this->option('page'),
        );

        $this->info("Compte #{$user->id} créé : {$user->email}".($user->is_admin ? ' (administrateur)' : ''));
        $this->line('  Sociétés : '.implode(', ', $codes));
        $this->warn("  Mot de passe provisoire : {$provisoire}");
        $this->line('  Changement forcé à la première connexion.');

        return self::SUCCESS;
    }
}
