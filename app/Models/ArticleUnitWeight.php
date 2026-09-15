<?php

namespace App\Models;

use App\Domain\Units\UnitWeightOrigin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Règle 2.8 — poids d'une unité de vente pour un article donné.
 * Alimente la page d'audit "facteurs de conversion" (section 4).
 */
class ArticleUnitWeight extends Model
{
    protected $fillable = [
        'article', 'designation', 'unite_vente', 'poids_unitaire_kg', 'origine',
        'designation_reference', 'en_conflit', 'conflit_details', 'corrige_par', 'corrige_le',
    ];

    protected $casts = [
        'poids_unitaire_kg' => 'float',
        'en_conflit' => 'boolean',
        'conflit_details' => 'array',
        'corrige_le' => 'datetime',
    ];

    public function correcteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par');
    }

    public function libelleOrigine(): string
    {
        return UnitWeightOrigin::libelle($this->origine);
    }

    public function poidsFiable(): bool
    {
        return $this->poids_unitaire_kg !== null && UnitWeightOrigin::estFiable($this->origine);
    }

    public function scopeAConfirmer($query)
    {
        return $query->whereIn('origine', [UnitWeightOrigin::HYPOTHESE_QUINTAL, UnitWeightOrigin::NON_DETERMINE])
            ->orWhere('en_conflit', true);
    }
}
