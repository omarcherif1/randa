<?php

namespace App\Domain\Import;

use App\Models\Anomalie;
use App\Models\ImportBatch;

/**
 * Règle 2.9 / section 4.8 — collecte les anomalies détectées pendant un import
 * (dédoublonnées par signature) puis les écrit dans la table `anomalies`.
 *
 * Si un administrateur a DÉJÀ tranché une anomalie de même signature
 * (statut "validee" ou "exclue") lors d'un import précédent, on ne la recrée pas :
 * sa décision est respectée.
 */
final class AnomalyRecorder
{
    /** @var array<string, array<string, mixed>> signature => attributs */
    private array $pending = [];

    public function add(
        string $type,
        string $severity,
        string $resume,
        string $signature,
        array $details = [],
        ?string $article = null,
    ): void {
        // première occurrence d'une signature = celle qu'on garde
        $this->pending[$signature] ??= [
            'type' => $type,
            'severity' => $severity,
            'resume' => $resume,
            'signature' => $signature,
            'article' => $article,
            'details' => $details ?: null,
        ];
    }

    public function count(): int
    {
        return count($this->pending);
    }

    /** @return list<string> signatures collectées */
    public function signatures(): array
    {
        return array_keys($this->pending);
    }

    public function flush(ImportBatch $batch): int
    {
        if ($this->pending === []) {
            return 0;
        }

        $signatures = array_keys($this->pending);

        $dejaTranchees = Anomalie::query()
            ->whereIn('signature', $signatures)
            ->whereIn('statut', [Anomalie::STATUT_VALIDEE, Anomalie::STATUT_EXCLUE])
            ->pluck('signature')
            ->all();
        $dejaTranchees = array_fill_keys($dejaTranchees, true);

        // Les anomalies "à vérifier" encore ouvertes des imports précédents pour ces
        // signatures : on les rattache au nouveau batch (on ne les empile pas).
        Anomalie::query()
            ->whereIn('signature', $signatures)
            ->where('statut', Anomalie::STATUT_A_VERIFIER)
            ->delete();

        $now = now();
        $rows = [];
        foreach ($this->pending as $signature => $attrs) {
            if (isset($dejaTranchees[$signature])) {
                continue;
            }
            $rows[] = [
                'import_batch_id' => $batch->id,
                'type' => $attrs['type'],
                'severity' => $attrs['severity'],
                'resume' => $attrs['resume'],
                'article' => $attrs['article'],
                'signature' => $signature,
                'details' => $attrs['details'] !== null ? json_encode($attrs['details']) : null,
                'statut' => Anomalie::STATUT_A_VERIFIER,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Anomalie::insert($chunk);
        }

        return count($rows);
    }
}
