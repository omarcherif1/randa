<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anomalie extends Model
{
    protected $fillable = [
        'import_batch_id', 'commande_ligne_id', 'commande_id',
        'type', 'severity', 'resume', 'article', 'signature', 'details',
        'statut', 'resolved_by', 'resolved_at', 'note',
    ];

    protected $casts = [
        'details' => 'array',
        'resolved_at' => 'datetime',
    ];

    public const STATUT_A_VERIFIER = 'a_verifier';

    public const STATUT_VALIDEE = 'validee';

    public const STATUT_EXCLUE = 'exclue';

    public function ligne(): BelongsTo
    {
        return $this->belongsTo(CommandeLigne::class, 'commande_ligne_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
