<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastSnapshot extends Model
{
    protected $fillable = [
        'article', 'designation', 'societe_id', 'mois_cible',
        'qte_commandee_prevue_kg', 'palier', 'fiabilite', 'tendance',
        'mois_historique', 'ajustement_saisonnier', 'qte_a_produire_kg',
        'import_batch_id', 'genere_le',
    ];

    protected $casts = [
        'qte_commandee_prevue_kg' => 'float',
        'qte_a_produire_kg' => 'float',
        'ajustement_saisonnier' => 'boolean',
        'genere_le' => 'datetime',
    ];

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }
}
