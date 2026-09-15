<?php

namespace App\Models;

use App\Domain\Classification\FamilyConsolidation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Règle 2.6 (complément) — mémorisation code article complet -> famille.
 * Alimente le même écran d'audit que les autres corrections de données (section 4).
 */
class ArticleFamily extends Model
{
    protected $table = 'article_families';

    protected $fillable = [
        'article', 'designation', 'famille_id', 'famille_code',
        'statut', 'historique', 'corrige_par', 'corrige_le',
    ];

    protected $casts = [
        'historique' => 'array',
        'corrige_le' => 'datetime',
    ];

    public function famille(): BelongsTo
    {
        return $this->belongsTo(Famille::class);
    }

    public function correcteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par');
    }

    public function estAmbigu(): bool
    {
        return $this->statut === FamilyConsolidation::STATUT_AMBIGU;
    }

    public function scopeAConfirmer($query)
    {
        return $query->whereIn('statut', [
            FamilyConsolidation::STATUT_AMBIGU,
            FamilyConsolidation::STATUT_RECLASSIFICATION,
        ]);
    }
}
