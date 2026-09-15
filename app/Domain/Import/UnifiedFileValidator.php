<?php

namespace App\Domain\Import;

/**
 * Valide un fichier unifié quotidien AVANT tout traitement (exigence 3.2 / 4.7).
 * On vérifie : lisibilité, taille raisonnable, présence de l'en-tête, présence
 * de TOUTES les colonnes logiques attendues (section 5), présence d'au moins
 * une ligne de données.
 */
final class UnifiedFileValidator
{
    private const TAILLE_MAX_OCTETS = 250 * 1024 * 1024; // 250 Mo

    public function validate(string $path): FileValidation
    {
        $errors = [];
        $warnings = [];

        if (! is_file($path) || ! is_readable($path)) {
            return new FileValidation(['Fichier introuvable ou illisible.']);
        }

        $taille = filesize($path);
        if ($taille === 0) {
            return new FileValidation(['Le fichier est vide.']);
        }
        if ($taille > self::TAILLE_MAX_OCTETS) {
            return new FileValidation([sprintf(
                'Fichier trop volumineux (%.0f Mo, maximum %d Mo).',
                $taille / 1024 / 1024, self::TAILLE_MAX_OCTETS / 1024 / 1024,
            )]);
        }

        try {
            $reader = new CsvReader($path);
            $header = $reader->header();
        } catch (\Throwable) {
            return new FileValidation(['Impossible de lire l\'en-tête du fichier (format CSV attendu, séparateur « ; »).']);
        }

        if (count($header) < 10) {
            return new FileValidation(['En-tête inattendu : trop peu de colonnes. Séparateur « ; » attendu.']);
        }

        $columns = new ColumnMap($header);
        $manquants = $columns->missingFields();
        if ($manquants !== []) {
            $errors[] = 'Colonnes attendues absentes : '.implode(', ', $manquants).'.';
        }

        if ($columns->familyColumnCandidates === []) {
            $warnings[] = 'Aucune colonne « Famille statistique » : toutes les lignes seront en famille inconnue.';
        }

        $premiereLigne = null;
        foreach ($reader->rows(1) as $r) {
            $premiereLigne = $r;
        }
        if ($premiereLigne === null) {
            $errors[] = 'Le fichier ne contient aucune ligne de données.';
        }

        return new FileValidation($errors, $warnings);
    }
}
