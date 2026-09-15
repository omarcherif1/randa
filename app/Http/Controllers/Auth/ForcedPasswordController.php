<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Access\PasswordPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Règle 3.2 — écran de changement de mot de passe forcé (premier login / reset).
 * L'utilisateur ne peut rien faire d'autre tant qu'il n'a pas changé son mot de passe.
 */
class ForcedPasswordController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ForcedPasswordChange');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => PasswordPolicy::rules(),
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($request->string('password')),
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('status', 'Mot de passe mis à jour.');
    }
}
