<?php

namespace App\Support;

/**
 * Normalisation de texte partagée (une seule implémentation dans toute l'app).
 * Sert notamment à la reconnaissance de type de client par mots-clés (règle 2.7).
 */
final class Text
{
    /**
     * Majuscules, sans accents, espaces compactés. Utilisé pour comparer des
     * noms de clients à une liste de mots-clés de façon robuste.
     */
    public static function normalize(?string $value): string
    {
        $value = self::stripAccents((string) $value);

        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return mb_strtoupper($value);
    }

    /**
     * Nettoie une valeur numérique venant du fichier source :
     * gère la virgule décimale ("201657,6"), les espaces, les valeurs vides.
     */
    public static function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $value = str_replace([' ', "\xC2\xA0"], '', $value);

        // "1.234,56" (format FR) -> "1234.56"
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Translittération accents -> ASCII, indépendante de la locale système
     * (iconv//TRANSLIT n'est pas fiable sous Windows).
     */
    public static function stripAccents(string $value): string
    {
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ß' => 'ss',
        ];

        $lower = mb_strtolower($value, 'UTF-8');
        $lower = strtr($lower, $map);

        // Retire tout caractère non ASCII restant.
        return preg_replace('/[^\x00-\x7F]/', '', $lower) ?? $lower;
    }
}
