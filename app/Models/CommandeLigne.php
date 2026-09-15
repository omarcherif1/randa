<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandeLigne extends Model
{
    protected $fillable = [
        'commande_id', 'produit_id', 'famille_id', 'article', 'designation',
        'unite_vente', 'famille_code_fichier', 'famille_source', 'etat_ligne',
        'qte_commandee_raw', 'qte_livree_raw', 'qte_facturee_raw',
        'qte_commandee_kg', 'qte_livree_kg', 'qte_facturee_kg',
        'poids_unitaire_kg', 'poids_origine', 'compte_volume', 'volume_fiable',
        'is_eligible', 'is_satisfied',
        'exclusion_reason', 'is_outlier', 'outlier_reason', 'line_hash',
    ];

    protected $casts = [
        'qte_commandee_raw' => 'float',
        'qte_livree_raw' => 'float',
        'qte_facturee_raw' => 'float',
        'qte_commandee_kg' => 'float',
        'qte_livree_kg' => 'float',
        'qte_facturee_kg' => 'float',
        'poids_unitaire_kg' => 'float',
        'compte_volume' => 'boolean',
        'volume_fiable' => 'boolean',
        'is_eligible' => 'boolean',
        'is_satisfied' => 'boolean',
        'is_outlier' => 'boolean',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function famille(): BelongsTo
    {
        return $this->belongsTo(Famille::class);
    }
}
