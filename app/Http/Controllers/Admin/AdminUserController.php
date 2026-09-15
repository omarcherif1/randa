<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccountService;
use App\Domain\Access\Page;
use App\Http\Controllers\Controller;
use App\Models\PermissionTemplate;
use App\Models\Societe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion des comptes et des droits (section 4.8) — chaque action est
 * journalisée par AccountService (règle 3.2).
 */
class AdminUserController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'is_admin' => ['boolean'],
            'societes' => ['array', 'min:1'],
            'societes.*' => ['integer', Rule::exists('societes', 'id')],
            'pages' => ['array'],
            'pages.*' => [Rule::in(Page::assignableKeys())],
        ]);

        [, $provisoire] = $this->accounts->creer(
            nom: $data['name'],
            email: $data['email'],
            admin: (bool) ($data['is_admin'] ?? false),
            societeIds: $data['societes'] ?? Societe::pluck('id')->all(),
            pageKeys: $data['pages'] ?? [],
        );

        return back()->with('status', "Compte créé. Mot de passe provisoire : {$provisoire}");
    }

    public function lock(Request $request, User $user): RedirectResponse
    {
        $this->interdireAutoAction($request, $user);
        $this->accounts->verrouiller($user, $request->string('motif')->toString() ?: null);

        return back()->with('status', 'Compte verrouillé.');
    }

    public function unlock(User $user): RedirectResponse
    {
        $this->accounts->deverrouiller($user);

        return back()->with('status', 'Compte déverrouillé.');
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $provisoire = $this->accounts->reinitialiserMotDePasse($user);

        return back()->with('status', "Mot de passe réinitialisé. Provisoire : {$provisoire}");
    }

    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_admin' => ['boolean'],
            'societes' => ['array', 'min:1'],
            'societes.*' => ['integer', Rule::exists('societes', 'id')],
            'pages' => ['array'],
            'pages.*' => [Rule::in(Page::assignableKeys())],
            'template' => ['nullable', Rule::exists('permission_templates', 'code')],
        ]);

        if (! empty($data['template'])) {
            $this->accounts->appliquerModele($user, PermissionTemplate::where('code', $data['template'])->firstOrFail());
        } else {
            $user->forceFill(['is_admin' => (bool) ($data['is_admin'] ?? false)])->save();
        }

        $this->accounts->definirSocietes($user, $data['societes'] ?? []);
        if (! $user->fresh()->is_admin) {
            $this->accounts->definirPages($user, $data['pages'] ?? []);
        }

        return back()->with('status', 'Droits mis à jour.');
    }

    private function interdireAutoAction(Request $request, User $user): void
    {
        abort_if($request->user()->is($user), 422, 'Vous ne pouvez pas verrouiller votre propre compte.');
    }
}
