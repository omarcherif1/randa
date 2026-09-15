<?php

namespace App\Console\Commands\User;

use App\Domain\Access\AccountService;
use App\Domain\Access\Page;
use App\Models\PermissionTemplate;
use App\Models\Societe;
use App\Models\User;
use Illuminate\Console\Command;

class AccessUserCommand extends Command
{
    protected $signature = 'randa:user:access
        {email}
        {--template= : Applique un modèle de permissions (code)}
        {--grant=* : Pages à accorder}
        {--revoke=* : Pages à retirer}
        {--set=* : Remplace la liste des pages par celle-ci}
        {--societe=* : Remplace le rattachement société (codes)}
        {--show : Affiche seulement l\'état actuel}';

    protected $description = 'Gère les droits d\'accès (pages, sociétés, modèle) d\'un compte, personne par personne';

    public function handle(AccountService $accounts): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('Utilisateur introuvable.');

            return self::FAILURE;
        }

        if ($this->option('show')) {
            return $this->afficher($user);
        }

        if ($code = $this->option('template')) {
            $modele = PermissionTemplate::where('code', $code)->first();
            if ($modele === null) {
                $this->error('Modèle inconnu. Codes : '.PermissionTemplate::pluck('code')->implode(', '));

                return self::FAILURE;
            }
            $accounts->appliquerModele($user, $modele);
            $this->info("Modèle « {$modele->nom} » appliqué.");
        }

        if ($this->option('societe')) {
            $ids = Societe::whereIn('code', $this->option('societe'))->pluck('id')->all();
            $accounts->definirSocietes($user, $ids);
            $this->info('Rattachement société mis à jour.');
        }

        if (! $user->fresh()->is_admin) {
            $pages = $user->pagePermissions()->pluck('page_key')->all();

            if ($set = $this->option('set')) {
                $pages = $set;
            } else {
                $pages = array_diff(
                    array_merge($pages, $this->option('grant')),
                    $this->option('revoke'),
                );
            }

            $invalides = array_diff($pages, Page::assignableKeys());
            if ($invalides !== []) {
                $this->error('Pages inconnues : '.implode(', ', $invalides));
                $this->line('Pages valides : '.implode(', ', Page::assignableKeys()));

                return self::FAILURE;
            }

            $accounts->definirPages($user, array_values(array_unique($pages)));
            $this->info('Droits de pages mis à jour.');
        }

        return $this->afficher($user->fresh());
    }

    private function afficher(User $user): int
    {
        $this->newLine();
        $this->line("Compte : {$user->email}".($user->is_admin ? '  [ADMINISTRATEUR — toutes les pages]' : ''));
        $this->line('Sociétés : '.($user->societes()->pluck('code')->implode(', ') ?: '(aucune — ne voit rien)'));
        if (! $user->is_admin) {
            $this->line('Pages   : '.($user->pagePermissions()->pluck('page_key')->implode(', ') ?: '(aucune)'));
        }
        $this->newLine();

        return self::SUCCESS;
    }
}
