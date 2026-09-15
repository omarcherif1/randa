<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produit extends Model
{
    protected $fillable = [
        'article', 'designation', 'famille_id', 'famille_source',
        'unite_vente_frequente', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function famille(): BelongsTo
    {
        return $this->belongsTo(Famille::class);
    }
}
