<?php

namespace App\Domain\Import;

use Generator;
use RuntimeException;

/**
 * Lecteur CSV en flux (streaming) — ne charge jamais tout le fichier en mémoire
 * (exigence de performance section 7 : le fichier unifié fait plusieurs Mo).
 *
 * Le fichier source du groupe Randa est séparé par des points-virgules. Son
 * encodage dépend de l'extraction :
 *   - Windows-1252 (Latin-1) — extraction historique, reconverti en UTF-8 ;
 *   - UTF-8 — extraction récente (F1790079066766), dont les accents ont DÉJÀ été
 *     remplacés par « � » (U+FFFD) à l'extraction : réparés par AccentRepair
 *     (lexique, candidat unique seulement — jamais devinés).
 * Sans encodage imposé, il est détecté sur le début du fichier.
 */
final class CsvReader
{
    public const REMPLACEMENT = "\u{FFFD}";

    private const ECHANTILLON_OCTETS = 262144;

    public readonly string $sourceEncoding;

    /** Le fichier contient des « � » : accents perdus à l'extraction. */
    public readonly bool $accentsPerdus;

    /** réparation des « � » d'après le lexique (null si le fichier n'en a pas) */
    public readonly ?AccentRepair $reparation;

    public function __construct(
        private readonly string $path,
        private readonly string $delimiter = ';',
        ?string $sourceEncoding = null,
    ) {
        if (! is_readable($this->path)) {
            throw new RuntimeException("Fichier illisible : {$this->path}");
        }

        $echantillon = (string) file_get_contents($this->path, false, null, 0, self::ECHANTILLON_OCTETS);
        $this->sourceEncoding = $sourceEncoding ?? self::detecterEncodage($echantillon);
        $this->accentsPerdus = $this->sourceEncoding === 'UTF-8'
            && str_contains($echantillon, self::REMPLACEMENT);
        $this->reparation = $this->accentsPerdus ? AccentRepair::fromConfig() : null;
    }

    /**
     * UTF-8 si l'échantillon est de l'UTF-8 valide (du Windows-1252 accentué ne
     * l'est quasiment jamais), sinon Windows-1252. Un caractère multi-octets coupé
     * en fin d'échantillon est écarté avant le test.
     */
    public static function detecterEncodage(string $echantillon): string
    {
        if (str_starts_with($echantillon, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        $finLigne = strrpos($echantillon, "\n");
        if ($finLigne !== false && strlen($echantillon) >= self::ECHANTILLON_OCTETS) {
            $echantillon = substr($echantillon, 0, $finLigne);
        }

        return mb_check_encoding($echantillon, 'UTF-8') ? 'UTF-8' : 'Windows-1252';
    }

    /** @return list<string> en-têtes normalisés en UTF-8 */
    public function header(): array
    {
        $handle = $this->open();
        try {
            $row = $this->decodeRow(fgetcsv($handle, 0, $this->delimiter, '"', '\\') ?: []);
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\x{FEFF}/u', '', $row[0]) ?? $row[0];
            }

            return $row;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Itère les lignes de données (hors en-tête) en tableaux indexés UTF-8.
     *
     * @return Generator<int, list<string>>
     */
    public function rows(?int $limit = null): Generator
    {
        $handle = $this->open();
        try {
            fgetcsv($handle, 0, $this->delimiter, '"', '\\'); // saute l'en-tête
            $i = 0;
            while (($row = fgetcsv($handle, 0, $this->delimiter, '"', '\\')) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }
                yield $i => $this->decodeRow($row);
                $i++;
                if ($limit !== null && $i >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param array<int, string|null> $row @return list<string> */
    private function decodeRow(array $row): array
    {
        return array_map(function ($cell) {
            $cell = (string) $cell;
            if ($this->sourceEncoding !== 'UTF-8') {
                $cell = mb_convert_encoding($cell, 'UTF-8', $this->sourceEncoding);
            } elseif ($this->reparation !== null) {
                $cell = $this->reparation->repair($cell);
            }

            return trim($cell);
        }, $row);
    }

    /** @return resource */
    private function open()
    {
        $handle = fopen($this->path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Ouverture impossible : {$this->path}");
        }

        return $handle;
    }
}
