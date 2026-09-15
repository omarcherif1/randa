<?php

namespace App\Domain\Access\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle 3.2 — refuse les mots de passe par défaut évidents.
 */
class NotCommonPassword implements ValidationRule
{
    /** @param list<string> $interdits */
    public function __construct(private readonly array $interdits) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalise = mb_strtolower(trim((string) $value));

        foreach ($this->interdits as $interdit) {
            if ($normalise === mb_strtolower($interdit)) {
                $fail('Ce mot de passe est trop courant. Choisissez-en un autre.');

                return;
            }
        }
    }
}
