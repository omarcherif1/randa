<?php

namespace App\Domain\Access;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Règle 3.2 — verrouillage temporaire après échecs de connexion répétés,
 * et historique des connexions (4.8).
 *
 * Distinct de la limitation de débit de Laravel (par IP+e-mail, courte) :
 * ici c'est un verrouillage PERSISTANT du compte après trop d'échecs.
 */
final class AccountSecurity
{
    private const MAX_ECHECS = 5;

    private const DUREE_VERROU_MINUTES = 15;

    public function estVerrouilleTemporairement(User $user): bool
    {
        return $user->locked_until !== null && $user->locked_until->isFuture();
    }

    public function secondesAvantDeverrouillage(User $user): int
    {
        if (! $this->estVerrouilleTemporairement($user)) {
            return 0;
        }

        return (int) now()->diffInSeconds($user->locked_until, false);
    }

    public function enregistrerEchec(?User $user, string $email, string $motif, Request $request): void
    {
        $this->journaliser($user, $email, false, $motif, $request);

        if ($user === null) {
            return;
        }

        $user->failed_login_attempts++;
        if ($user->failed_login_attempts >= self::MAX_ECHECS) {
            $user->locked_until = now()->addMinutes(self::DUREE_VERROU_MINUTES);
            $user->failed_login_attempts = 0;
        }
        $user->save();
    }

    public function enregistrerSucces(User $user, Request $request): void
    {
        $this->journaliser($user, $user->email, true, null, $request);

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();
    }

    private function journaliser(?User $user, string $email, bool $succes, ?string $motif, Request $request): void
    {
        LoginAttempt::create([
            'user_id' => $user?->id,
            'email' => mb_substr($email, 0, 191),
            'successful' => $succes,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'motif_echec' => $motif,
            'created_at' => now(),
        ]);
    }
}
