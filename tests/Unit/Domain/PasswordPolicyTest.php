<?php

namespace Tests\Unit\Domain;

use App\Domain\Access\PasswordPolicy;
use App\Domain\Access\Rules\NotCommonPassword;
use PHPUnit\Framework\TestCase;

/**
 * Règle 3.2 — politique de mot de passe minimale.
 */
class PasswordPolicyTest extends TestCase
{
    public function test_refuse_les_mots_de_passe_evidents(): void
    {
        $this->assertTrue(PasswordPolicy::estInterdit('password'));
        $this->assertTrue(PasswordPolicy::estInterdit('Randa2026'));
        $this->assertTrue(PasswordPolicy::estInterdit('  ADMIN '));
        $this->assertFalse(PasswordPolicy::estInterdit('Tr8mbone-Cactus'));
    }

    public function test_la_regle_not_common_password_echoue_sur_un_mot_interdit(): void
    {
        $rule = new NotCommonPassword(['password', 'randa']);

        $erreurs = [];
        $rule->validate('password', 'RANDA', function ($m) use (&$erreurs) {
            $erreurs[] = $m;
        });

        $this->assertNotEmpty($erreurs);
    }

    public function test_longueur_minimale_documentee(): void
    {
        $this->assertGreaterThanOrEqual(10, PasswordPolicy::LONGUEUR_MIN);
        $this->assertContains('required', PasswordPolicy::rules());
        $this->assertContains('confirmed', PasswordPolicy::rules());
    }
}
