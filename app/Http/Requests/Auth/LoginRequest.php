<?php

namespace App\Http\Requests\Auth;

use App\Domain\Access\AccountSecurity;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Authentifie la requête (section 3.2 : verrouillage compte, verrouillage
     * temporaire après échecs, historique des connexions).
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $security = app(AccountSecurity::class);
        $email = (string) $this->string('email');
        $user = User::where('email', mb_strtolower(trim($email)))->first();

        // Message générique dans tous les cas de refus (ne révèle pas l'état du compte).
        $refus = fn () => ValidationException::withMessages(['email' => trans('auth.failed')]);

        if ($user !== null && $user->isLocked()) {
            $security->enregistrerEchec($user, $email, 'compte_verrouille', $this);
            throw $refus();
        }

        if ($user !== null && $security->estVerrouilleTemporairement($user)) {
            $security->enregistrerEchec($user, $email, 'verrouillage_temporaire', $this);
            $minutes = (int) ceil($security->secondesAvantDeverrouillage($user) / 60);
            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessayez dans {$minutes} minute(s).",
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            $security->enregistrerEchec($user, $email, 'identifiants', $this);
            throw $refus();
        }

        // Verrouillé entre la vérification et l'authentification (course).
        if ($this->user()->isLocked()) {
            Auth::guard('web')->logout();
            $security->enregistrerEchec($this->user(), $email, 'compte_verrouille', $this);
            throw $refus();
        }

        RateLimiter::clear($this->throttleKey());
        $security->enregistrerSucces($this->user(), $this);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
