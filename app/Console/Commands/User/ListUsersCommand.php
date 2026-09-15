<?php

namespace App\Console\Commands\User;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsersCommand extends Command
{
    protected $signature = 'randa:user:list';

    protected $description = 'Liste les comptes, leur état et leurs rattachements société';

    public function handle(): int
    {
        $rows = User::with('societes')->orderBy('name')->get()->map(fn (User $u) => [
            $u->id,
            $u->email,
            $u->name,
            $u->is_admin ? 'admin' : '',
            $u->isLocked() ? 'VERROUILLÉ' : ($u->locked_until?->isFuture() ? 'temp.' : 'actif'),
            $u->must_change_password ? 'oui' : '',
            $u->societes->pluck('code')->implode(','),
            $u->last_login_at?->format('Y-m-d H:i') ?? '—',
        ])->all();

        $this->table(
            ['#', 'E-mail', 'Nom', 'Rôle', 'État', 'Chg. MDP', 'Sociétés', 'Dernière connexion'],
            $rows,
        );

        return self::SUCCESS;
    }
}
