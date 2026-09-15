<?php

namespace App\Models;

use Database\Factories\SocieteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Societe extends Model
{
    /** @use HasFactory<SocieteFactory> */
    use HasFactory;

    protected $table = 'societes';

    protected $fillable = ['code', 'nom', 'couleur', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }
}
