<?php

namespace App\Domain\Import;

use Generator;
use RuntimeException;

/**
 * Lecteur CSV en flux (streaming) — ne charge jamais tout le fichier en mémoire
 * (exigence de performance section 7 : le fichier unifié fait plusieurs Mo).
 *
 * Le fichier source du groupe Randa est :
 *   - séparé par des points-virgules ;
 *   - encodé en Windows-1252 (Latin-1) — reconverti en UTF-8 à la lecture.
 */
final class CsvReader
{
    public function __construct(
        private readonly string $path,
        private readonly string $delimiter = ';',
        private readonly string $sourceEncoding = 'Windows-1252',
    ) {
        if (! is_readable($this->path)) {
            throw new RuntimeException("Fichier illisible : {$this->path}");
        }
    }

    /** @return list<string> en-têtes normalisés en UTF-8 */
    public function header(): array
    {
        $handle = $this->open();
        try {
            $row = fgetcsv($handle, 0, $this->delimiter, '"', '\\');

            return $this->decodeRow($row ?: []);
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
