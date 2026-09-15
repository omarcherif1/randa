<?php

namespace App\Domain\Import;

/**
 * Résultat de la validation d'un fichier AVANT traitement (exigence 3.2 / 4.7 :
 * structure, colonnes attendues, taille — jamais transmis tel quel au moteur).
 */
final class FileValidation
{
    /**
     * @param  list<string>  $errors  bloquants : l'import n'a pas lieu
     * @param  list<string>  $warnings  non bloquants : l'import a lieu, signalés au journal
     */
    public function __construct(
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}

    public function ok(): bool
    {
        return $this->errors === [];
    }

    public function messageErreur(): string
    {
        return implode(' ', $this->errors);
    }
}
