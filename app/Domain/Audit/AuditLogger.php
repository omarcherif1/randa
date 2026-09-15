<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Règle 3.2 — journalise les actions sensibles : QUI, QUOI, QUAND.
 * Un seul point d'entrée pour tout le code.
 */
final class AuditLogger
{
    public function record(
        string $action,
        string $description,
        ?Model $cible = null,
        array $metadata = [],
        ?User $acteur = null,
    ): AuditLog {
        $acteur ??= Auth::user();

        return AuditLog::create([
            'user_id' => $acteur?->id,
            'acteur_libelle' => $acteur?->name ?? 'Système',
            'action' => $action,
            'cible_type' => $cible !== null ? class_basename($cible) : null,
            'cible_id' => $cible?->getKey() !== null ? (string) $cible->getKey() : null,
            'description' => mb_substr($description, 0, 255),
            'metadata' => $metadata !== [] ? $metadata : null,
            'ip' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
