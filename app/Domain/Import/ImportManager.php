<?php

namespace App\Domain\Import;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Forecast\ForecastSnapshotWriter;
use App\Domain\Reporting\MetricsCache;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Point d'entrée UNIQUE de l'import (section 4.7).
 *
 * Reçoit un fichier + un type, l'archive, crée l'entrée de journal (`import_batches`),
 * délègue au moteur adapté, et garantit qu'un échec technique n'expose jamais
 * de détail à l'utilisateur (exigence 3.2) : le détail va dans les logs, un
 * message générique dans le journal.
 *
 * Volontairement découplé de HTTP : réutilisable tel quel par un dépôt/synchro
 * automatique en V3.
 */
final class ImportManager
{
    public const TYPE_UNIFIED = 'unified';

    public const TYPE_STOCK = 'stock';

    public function __construct(
        private readonly UnifiedFileImporter $unified = new UnifiedFileImporter,
        private readonly StockFileImporter $stock = new StockFileImporter,
    ) {}

    /**
     * @param  string  $sourcePath  chemin local du fichier à importer
     * @param  string  $originalName  nom d'origine (affiché dans le journal)
     * @param  string  $type  self::TYPE_UNIFIED | self::TYPE_STOCK
     */
    public function import(string $sourcePath, string $originalName, string $type = self::TYPE_UNIFIED, ?int $userId = null): ImportBatch
    {
        $storedPath = $this->archive($sourcePath, $originalName);

        $batch = ImportBatch::create([
            'type' => $type,
            'original_filename' => $originalName,
            'stored_path' => $storedPath,
            'user_id' => $userId,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $absolute = Storage::disk('local')->path($storedPath);

        try {
            $result = match ($type) {
                self::TYPE_UNIFIED => $this->unified->import($absolute, $batch),
                self::TYPE_STOCK => $this->stock->import($absolute, $batch),
                default => throw new ImportValidationException("Type d'import inconnu : {$type}."),
            };

            $batch->fill($result->toBatchAttributes());
            $batch->status = 'completed';
            $batch->finished_at = now();
            $batch->save();

            // Exigence 7 — invalide TOUS les agrégats de l'application (rafraîchis à chaque import).
            MetricsCache::bump();

            // Règle 4.5 — enregistre la photo des prévisions (accumule l'historique
            // « prévu vs réel »). N'échoue jamais l'import (déjà validé) si le calcul plante.
            if ($type === self::TYPE_UNIFIED) {
                try {
                    (new ForecastSnapshotWriter)->run($batch);
                } catch (Throwable $e) {
                    Log::warning('Snapshot de prévisions non enregistré', ['batch' => $batch->id, 'message' => $e->getMessage()]);
                }
            }

            (new AuditLogger)->record(
                AuditAction::IMPORT_LANCE,
                "Import « {$originalName} » ({$type}) — {$batch->rows_total} lignes, {$batch->anomalies_count} anomalie(s)",
                $batch,
                ['commandes_new' => $batch->commandes_new, 'commandes_replaced' => $batch->commandes_replaced],
            );
        } catch (ImportValidationException $e) {
            $this->fail($batch, $e->getMessage(), $e);
        } catch (Throwable $e) {
            $this->fail($batch, 'Erreur pendant le traitement du fichier. Consultez les journaux techniques.', $e);
        }

        return $batch->refresh();
    }

    private function archive(string $sourcePath, string $originalName): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'csv';
        $stored = 'imports/'.now()->format('Y/m/d').'/'.Str::uuid()->toString().'.'.$ext;
        Storage::disk('local')->put($stored, file_get_contents($sourcePath));

        return $stored;
    }

    private function fail(ImportBatch $batch, string $message, Throwable $e): void
    {
        Log::error('Import échoué', [
            'batch_id' => $batch->id,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        $batch->status = 'failed';
        $batch->error_message = $message;
        $batch->finished_at = now();
        $batch->save();

        // L'état du journal d'import a changé — l'alerte « dernier import échoué »
        // (section 6) doit pouvoir apparaître tout de suite.
        MetricsCache::bump();
    }
}
