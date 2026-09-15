<?php

namespace App\Domain\Import;

use App\Models\ImportBatch;
use App\Models\Produit;
use App\Models\StockSnapshot;
use App\Support\Text;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Règle 2.5 méthode A / section 4.7 — import du fichier de stock journalier.
 *
 * Format attendu (simple, à ajuster quand le site fournira un vrai fichier) :
 *   CSV « ; », en-tête, colonnes : Article ; Date ; Stock (kg)
 *
 * Dédoublonnage par remplacement : une même paire (article, date) réimportée
 * remplace la valeur précédente.
 */
final class StockFileImporter
{
    public function import(string $path, ImportBatch $batch): ImportResult
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new ImportValidationException('Fichier de stock introuvable ou vide.');
        }

        $reader = new CsvReader($path);
        $header = array_map(fn ($h) => Text::normalize($h), $reader->header());
        $iArticle = $this->col($header, ['ARTICLE']);
        $iDate = $this->col($header, ['DATE', 'DATE STOCK']);
        $iQte = $this->col($header, ['STOCK KG', 'STOCK', 'QUANTITE KG', 'QUANTITE', 'QTE']);

        if ($iArticle === null || $iDate === null || $iQte === null) {
            throw new ImportValidationException('En-tête du fichier de stock inattendu : colonnes Article / Date / Stock attendues.');
        }

        $result = new ImportResult;
        $produitIds = Produit::pluck('id', 'article')->all();
        $now = now();
        $rows = [];

        foreach ($reader->rows() as $row) {
            $result->rowsTotal++;
            $article = trim($row[$iArticle] ?? '');
            $date = $this->parseDate(trim($row[$iDate] ?? ''));
            $qte = Text::toFloat($row[$iQte] ?? '');

            if ($article === '' || $date === null) {
                $result->rowsSkipped++;

                continue;
            }

            $rows[] = [
                'article' => $article,
                'produit_id' => $produitIds[$article] ?? null,
                'date_stock' => $date->toDateString(),
                'quantite_kg' => round($qte, 3),
                'import_batch_id' => $batch->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows, &$result) {
            foreach (array_chunk($rows, 1000) as $chunk) {
                StockSnapshot::upsert(
                    $chunk,
                    ['article', 'date_stock'],
                    ['produit_id', 'quantite_kg', 'import_batch_id', 'updated_at'],
                );
                $result->rowsImported += count($chunk);
            }
        });

        $result->stats = ['snapshots' => $result->rowsImported];

        return $result;
    }

    /** @param list<string> $header */
    private function col(array $header, array $candidats): ?int
    {
        foreach ($header as $i => $h) {
            if (in_array($h, $candidats, true)) {
                return $i;
            }
        }

        return null;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $fmt) {
            try {
                $d = CarbonImmutable::createFromFormat($fmt, $value);
                if ($d !== false) {
                    return $d->startOfDay();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
