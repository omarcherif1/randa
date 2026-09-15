<?php

namespace App\Domain\Access;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/**
 * Règle 3.2 — politique de mot de passe minimale obligatoire :
 * longueur, refus des mots de passe par défaut évidents.
 * Vérification 100 % hors-ligne (pas d'appel réseau).
 */
final class PasswordPolicy
{
    public const LONGUEUR_MIN = 10;

    /** Mots de passe « évidents » refusés (comparaison insensible à la casse). */
    private const INTERDITS = [
        'password', 'motdepasse', 'azerty', 'qwerty', 'qwertyuiop', 'azertyuiop',
        '12345678', '123456789', '1234567890', 'admin', 'administrateur', 'root',
        'randa', 'randa2024', 'randa2025', 'randa2026', 'welcome', 'bienvenue',
        'changeme', 'password1', 'passw0rd', 'letmein', 'iloveyou', 'secret',
    ];

    /**
     * @return array<int, Rule|ValidationRule|string>
     */
    public static function rules(): array
    {
        return [
            'required', 'string', 'confirmed',
            Password::min(self::LONGUEUR_MIN)->letters()->numbers(),
            new Rules\NotCommonPassword(self::INTERDITS),
        ];
    }

    public static function estInterdit(string $motDePasse): bool
    {
        $normalise = mb_strtolower(trim($motDePasse));

        return in_array($normalise, array_map('mb_strtolower', self::INTERDITS), true);
    }
}
