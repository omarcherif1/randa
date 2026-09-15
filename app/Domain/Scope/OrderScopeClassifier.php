<?php

namespace App\Domain\Scope;

/**
 * Règle métier 2.2 — le caractère export/local d'une commande est déterminé
 * UNIQUEMENT par le préfixe du numéro de commande :
 *
 *   - commence par "EXP"  => export   (ex. EXPRAN01260138)
 *   - commence par "CM"   => local    (ex. CMRAN012605170)
 *
 * On ne se base JAMAIS sur le site de vente ou un autre champ (section 5).
 *
 * Le code société (RAN / SMT / TF...) est le segment situé entre le préfixe
 * et les chiffres.
 */
final class OrderScopeClassifier
{
    public function scope(string $numeroCommande): OrderScope
    {
        $n = strtoupper(trim($numeroCommande));

        if (str_starts_with($n, 'EXP')) {
            return OrderScope::Export;
        }

        if (str_starts_with($n, 'CM')) {
            return OrderScope::Local;
        }

        // Numéro non reconnu : traité comme local mais signalé en amont (anomalie).
        return OrderScope::Local;
    }

    public function isRecognized(string $numeroCommande): bool
    {
        $n = strtoupper(trim($numeroCommande));

        return str_starts_with($n, 'EXP') || str_starts_with($n, 'CM');
    }

    /**
     * Code société extrait du numéro : "CMRAN0126..." => "RAN", "EXPSMT01..." => "SMT".
     */
    public function societeCode(string $numeroCommande): ?string
    {
        $n = strtoupper(trim($numeroCommande));

        if (preg_match('/^(?:EXP|CM)([A-Z]+)/', $n, $m)) {
            return $m[1];
        }

        return null;
    }
}
