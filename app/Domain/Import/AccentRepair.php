<?php

namespace App\Domain\Import;

/**
 * Répare les accents remplacés par « � » (U+FFFD) à l'extraction Sage.
 *
 * Déterministe, jamais deviné : un mot abîmé (« D�chet ») est comparé au lexique
 * (`config/lexique_accents.php`) — chaque « � » vaut UNE lettre non ASCII, les
 * autres lettres doivent correspondre (casse ignorée). Réparé seulement s'il
 * n'y a qu'UN candidat ; la casse du mot abîmé est conservée (« SP�CIALE » →
 * « SPÉCIALE »). Sans candidat unique, le « � » est retiré pour l'affichage et
 * le mot est remonté dans `nonRepares()` pour enrichir le lexique.
 */
final class AccentRepair
{
    /** @var array<string, list<string>> clé (minuscules, accent => '?') => mots du lexique */
    private array $index = [];

    /** @var array<string, string> cache mot abîmé => réparation */
    private array $cache = [];

    /** @var array<string, int> mot abîmé non réparé => occurrences */
    private array $nonRepares = [];

    /** @param list<string> $lexique */
    public function __construct(array $lexique)
    {
        foreach (array_unique($lexique) as $mot) {
            $cle = self::cle($mot);
            if (! in_array($mot, $this->index[$cle] ?? [], true)) {
                $this->index[$cle][] = $mot;
            }
        }
    }

    public static function fromConfig(): self
    {
        $lexique = function_exists('config') ? config('lexique_accents') : null;
        if (! is_array($lexique)) {
            $lexique = require dirname(__DIR__, 3).'/config/lexique_accents.php';
        }

        return new self($lexique);
    }

    public function repair(string $texte): string
    {
        if (! str_contains($texte, CsvReader::REMPLACEMENT)) {
            return $texte;
        }

        return preg_replace_callback(
            '/[\p{L}\x{FFFD}]*\x{FFFD}[\p{L}\x{FFFD}]*/u',
            function (array $m) {
                $repare = $this->cache[$m[0]] ??= $this->repairMot($m[0]);
                if (str_contains($m[0], CsvReader::REMPLACEMENT) && isset($this->nonRepares[$m[0]])) {
                    $this->nonRepares[$m[0]]++;
                }

                return $repare;
            },
            $texte,
        ) ?? $texte;
    }

    /** @return array<string, int> */
    public function nonRepares(): array
    {
        return $this->nonRepares;
    }

    private function repairMot(string $abime): string
    {
        $candidats = array_values(array_filter(
            $this->index[self::cle($abime)] ?? [],
            fn (string $mot) => mb_strlen($mot) === mb_strlen($abime),
        ));
        if (count($candidats) !== 1) {
            $this->nonRepares[$abime] = 0; // compté à chaque occurrence par repair()

            return str_replace(CsvReader::REMPLACEMENT, '', $abime);
        }

        $lettres = preg_replace('/[^\p{L}]/u', '', str_replace(CsvReader::REMPLACEMENT, '', $abime)) ?? '';
        $majuscules = $lettres !== '' && mb_strtoupper($lettres) === $lettres && mb_strtolower($lettres) !== $lettres;

        $source = mb_str_split($abime);
        $modele = mb_str_split($candidats[0]);
        $out = '';
        foreach ($source as $i => $car) {
            if ($car !== CsvReader::REMPLACEMENT) {
                $out .= $car;
            } elseif ($majuscules || ($i === 0 && count($source) > 1 && mb_strtoupper($source[1]) === $source[1])) {
                $out .= mb_strtoupper($modele[$i]);
            } else {
                $out .= mb_strtolower($modele[$i]);
            }
        }

        return $out;
    }

    /** minuscules, chaque lettre non ASCII (ou « � ») remplacée par « ? » */
    private static function cle(string $mot): string
    {
        $mot = str_replace(CsvReader::REMPLACEMENT, '?', mb_strtolower($mot));

        return preg_replace('/[^\x00-\x7F]/u', '?', $mot) ?? $mot;
    }
}
