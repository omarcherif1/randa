<?php

namespace App\Models;

use App\Domain\Access\SocieteScope;
use App\Domain\Scope\OrderScope;
use Database\Factories\CommandeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commande extends Model
{
    /** @use HasFactory<CommandeFactory> */
    use HasFactory;

    protected $fillable = [
        'numero', 'scope', 'societe_id', 'client_id', 'import_batch_id',
        'date_commande', 'site_vente', 'ville_commande', 'adresse_livraison',
        'etat', 'etat_incertain', 'numero_livraison', 'numero_facture', 'date_facture',
        'qte_commandee_kg', 'qte_livree_kg', 'qte_facturee_kg',
        'lignes_total', 'lignes_eligibles', 'lignes_satisfaites',
        'is_excluded', 'has_outlier',
    ];

    protected $casts = [
        'date_commande' => 'date',
        'date_facture' => 'date',
        'etat_incertain' => 'boolean',
        'is_excluded' => 'boolean',
        'has_outlier' => 'boolean',
        'qte_commandee_kg' => 'float',
        'qte_livree_kg' => 'float',
        'qte_facturee_kg' => 'float',
    ];

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(CommandeLigne::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** Règle 2.2 : le tableau de bord principal ne voit que le local. */
    public function scopeLocal(Builder $q): Builder
    {
        return $q->where('scope', OrderScope::Local->value);
    }

    public function scopeExport(Builder $q): Builder
    {
        return $q->where('scope', OrderScope::Export->value);
    }

    /** Commandes réellement prises en compte dans les indicateurs (règles 2.1 / 2.9). */
    public function scopeRetenues(Builder $q): Builder
    {
        return $q->where('is_excluded', false)->where('has_outlier', false);
    }

    /**
     * Règle 3.1 / confidentialité par société (défaut V1 corrigé) : ne renvoie que
     * les commandes des sociétés auxquelles la personne est rattachée
     * (toutes si administrateur, aucune si rattachée à aucune société).
     */
    public function scopeVisiblePour(Builder $q, ?User $user): Builder
    {
        return (new SocieteScope)->apply($q, $user);
    }
}
