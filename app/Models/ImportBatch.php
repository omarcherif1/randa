<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'type', 'original_filename', 'stored_path', 'user_id', 'status', 'error_message',
        'rows_total', 'rows_imported', 'rows_skipped', 'commandes_new', 'commandes_replaced',
        'lignes_count', 'clients_new', 'produits_new', 'anomalies_count', 'familles_inconnues_count',
        'famille_colonne_index', 'famille_colonnes_candidates', 'warnings', 'stats',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'famille_colonnes_candidates' => 'array',
        'warnings' => 'array',
        'stats' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomalie::class);
    }
}
