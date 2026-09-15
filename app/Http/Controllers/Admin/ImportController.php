<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditLogger;
use App\Domain\Import\ImportManager;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Import web du fichier unifié quotidien / fichier stock (section 4.7).
 *
 * `ImportManager` était déjà conçu, dès le départ, pour être appelé aussi bien
 * en CLI (`randa:import`) qu'en web sans aucune adaptation (voir
 * docs/ARCHITECTURE.md « Ce qui n'est pas encore fait ») : cette page ne fait
 * que lui passer le fichier reçu, elle ne réimplémente rien.
 */
class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Import', [
            'imports' => ImportBatch::latest()->limit(20)->get([
                'id', 'type', 'original_filename', 'status', 'error_message',
                'rows_total', 'rows_imported', 'rows_skipped',
                'commandes_new', 'commandes_replaced', 'anomalies_count',
                'created_at', 'finished_at',
            ]),
        ]);
    }

    public function store(Request $request, ImportManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt', 'max:51200'],
            'type' => ['required', 'in:unified,stock'],
        ]);

        $fichier = $data['fichier'];

        // Le fichier unifié quotidien peut dépasser 80 000 lignes : le traiter
        // en HTTP (contrairement au CLI, qui n'a pas cette limite par défaut)
        // dépassait la limite d'exécution PHP et tuait le processus en plein
        // milieu — l'import restait alors bloqué sur « en cours » pour
        // toujours, `ImportManager::fail()` n'ayant jamais pu s'exécuter.
        set_time_limit(0);

        $batch = $manager->import(
            $fichier->getRealPath(),
            $fichier->getClientOriginalName(),
            $data['type'],
            $request->user()->id,
        );

        if ($batch->status !== 'completed') {
            return back()->with('erreur', "Import « {$batch->original_filename} » échoué : {$batch->error_message}");
        }

        return back()->with(
            'status',
            "Import « {$batch->original_filename} » terminé — {$batch->rows_imported} ligne(s) importée(s), "
                .($data['type'] === 'unified'
                    ? "{$batch->commandes_new} commande(s) nouvelle(s), {$batch->commandes_replaced} remplacée(s)."
                    : 'stock mis à jour.'),
        );
    }

    /**
     * Retire un import de l'historique — le fichier source archivé est
     * supprimé, mais AUCUNE donnée métier n'est touchée (commandes, lignes,
     * anomalies…) : la clé `import_batch_id` qu'elles portent est juste mise
     * à NULL (contrainte `nullOnDelete` posée dès la migration), jamais les
     * lignes elles-mêmes. Pour annuler réellement un import (supprimer les
     * commandes qu'il a créées), voir la sauvegarde/restauration (3.2).
     */
    public function destroy(ImportBatch $batch): RedirectResponse
    {
        $description = "{$batch->original_filename} ({$batch->type}, {$batch->status})";

        if ($batch->stored_path) {
            Storage::disk('local')->delete($batch->stored_path);
        }
        $batch->delete();

        (new AuditLogger)->record(
            AuditAction::DONNEES_SUPPRIMEES,
            "Import retiré de l'historique : {$description}",
        );

        return back()->with('status', "Import « {$batch->original_filename} » retiré de la liste.");
    }
}
