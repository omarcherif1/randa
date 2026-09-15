<?php

namespace App\Domain\Units;

/**
 * Règle 2.8 — extrait le poids réel d'UNE unité de vente depuis le texte de la
 * désignation du produit (ex. "Semoule_fine_20KG" -> 20 kg,
 * "Farine PS-7 1 KG AT" -> 1 kg, "Semoule SSSE 50 Kg AT" -> 50 kg).
 *
 * Cherche un nombre (entier ou décimal, virgule ou point) suivi de "KG",
 * insensible à la casse, avec ou sans espace.
 *
 * Si la désignation contient PLUSIEURS poids différents, on ne devine pas :
 * la méthode renvoie null (poids indéterminé) et l'appelant signale l'ambiguïté.
 */
final class DesignationWeightExtractor
{
    private const PATTERN = '/(\d+(?:[.,]\d+)?)\s*KG\b/i';

    /**
     * @return float|null poids en kg d'une unité de vente, ou null si introuvable/ambigu
     */
    public function extract(?string $designation): ?float
    {
        $poids = $this->allWeights($designation);

        return count($poids) === 1 ? $poids[0] : null;
    }

    /**
     * @return list<float> tous les poids distincts trouvés dans la désignation, triés
     */
    public function allWeights(?string $designation): array
    {
        $designation = (string) $designation;
        if ($designation === '' || ! preg_match_all(self::PATTERN, $designation, $matches)) {
            return [];
        }

        $poids = [];
        foreach ($matches[1] as $raw) {
            $valeur = (float) str_replace(',', '.', $raw);
            if ($valeur > 0) {
                $poids[(string) $valeur] = $valeur;
            }
        }

        $poids = array_values($poids);
        sort($poids);

        return $poids;
    }

    public function isAmbiguous(?string $designation): bool
    {
        return count($this->allWeights($designation)) > 1;
    }
}
