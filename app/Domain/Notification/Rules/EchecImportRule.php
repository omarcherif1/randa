<?php

namespace App\Domain\Notification\Rules;

use App\Domain\Notification\Alert;
use App\Domain\Notification\AlertRule;
use App\Models\ImportBatch;
use App\Models\User;

/**
 * Section 6 (extension) — le dernier import du fichier unifié a échoué.
 *
 * Exemple d'extensibilité : ajoutée sans toucher à la structure du centre de
 * notifications, réservée aux personnes qui ont accès à l'Administration.
 */
final class EchecImportRule implements AlertRule
{
    public function cle(): string
    {
        return 'echec_import';
    }

    public function pageRequise(): ?string
    {
        return 'administration';
    }

    public function evaluer(?User $user): array
    {
        $dernier = ImportBatch::query()
            ->where('type', 'unified')
            ->latest('id')
            ->first();

        if ($dernier === null || $dernier->status !== 'failed') {
            return [];
        }

        return [new Alert(
            type: 'echec_import',
            severity: Alert::CRITIQUE,
            titre: 'Dernier import échoué',
            detail: "« {$dernier->original_filename} » — ".($dernier->error_message ?: 'voir les journaux techniques').
                ' Les chiffres affichés datent de l\'import précédent.',
            routeName: 'admin.index',
            reference: 'echec_import:'.$dernier->id,
        )];
    }
}
