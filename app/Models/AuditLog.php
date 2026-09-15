<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'acteur_libelle', 'action', 'cible_type', 'cible_id',
        'description', 'metadata', 'ip', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
