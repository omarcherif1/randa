<?php

namespace App\Domain\Classification;

use App\Support\Text;

/**
 * Règle métier 2.6 — le fichier source contient PLUSIEURS colonnes "Famille
 * statistique" à une position variable. Le système détecte automatiquement
 * laquelle contient un vrai code appartenant à la table de référence
 * (les 32 familles officielles) — jamais de "3ᵉ colonne" codée en dur.
 *
 * Méthode : pour chaque colonne candidate (en-tête = "Famille statistique"),
 * on mesure sur un échantillon de lignes :
 *   - la couverture : % de lignes où la colonne est renseignée ;
 *   - la validité   : % de valeurs renseignées qui sont un code famille connu.
 * On retient la colonne la plus à GAUCHE qui dépasse les deux seuils.
 * Toutes les candidates sont journalisées ; un admin peut forcer un autre choix
 * dans Paramètres (setting `famille_colonne_index`).
 */
final class FamilyColumnDetector
{
    private const HEADER_LABEL = 'famille statistique';

    private const MIN_COUVERTURE = 0.80;

    private const MIN_VALIDITE = 0.95;

    /**
     * @param  list<string>  $header  en-têtes du fichier (déjà en UTF-8)
     * @param  iterable<array<int,string>>  $rows  échantillon de lignes de données
     * @param  list<string>  $codesReference  codes des 32 familles officielles
     */
    public function detect(array $header, iterable $rows, array $codesReference): FamilyColumnResult
    {
        $refset = array_fill_keys(array_map('strval', $codesReference), true);

        // Colonnes candidates : en-tête intitulé "Famille statistique".
        $candidateIdx = [];
        foreach ($header as $i => $label) {
            if ($this->normalizeLabel($label) === self::HEADER_LABEL) {
                $candidateIdx[] = $i;
            }
        }

        $nonEmpty = array_fill_keys($candidateIdx, 0);
        $valid = array_fill_keys($candidateIdx, 0);
        $distinctValid = array_fill_keys($candidateIdx, []);
        $total = 0;

        foreach ($rows as $row) {
            $total++;
            foreach ($candidateIdx as $i) {
                $v = trim((string) ($row[$i] ?? ''));
                if ($v === '') {
                    continue;
                }
                $nonEmpty[$i]++;
                if (isset($refset[$v])) {
                    $valid[$i]++;
                    $distinctValid[$i][$v] = true;
                }
            }
        }

        $scored = [];
        foreach ($candidateIdx as $i) {
            $couverture = $total > 0 ? $nonEmpty[$i] / $total : 0.0;
            $validite = $nonEmpty[$i] > 0 ? $valid[$i] / $nonEmpty[$i] : 0.0;
            $scored[] = [
                'index' => $i,
                'entete' => $header[$i] ?? '',
                'couverture' => round($couverture, 4),
                'validite' => round($validite, 4),
                'codes_distincts' => count($distinctValid[$i]),
                'retenable' => $couverture >= self::MIN_COUVERTURE && $validite >= self::MIN_VALIDITE,
            ];
        }

        // Choix : la colonne retenable la plus à gauche.
        $chosen = null;
        foreach ($scored as $c) {
            if ($c['retenable']) {
                $chosen = $c['index'];
                break;
            }
        }

        return new FamilyColumnResult(
            chosenIndex: $chosen,
            candidates: $scored,
            sampleSize: $total,
        );
    }

    private function normalizeLabel(string $label): string
    {
        $label = Text::stripAccents($label);

        return trim(preg_replace('/\s+/', ' ', $label) ?? '');
    }
}
