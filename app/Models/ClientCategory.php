<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientCategory extends Model
{
    protected $fillable = ['code', 'intitule', 'intitule_court', 'is_export', 'is_fallback'];

    protected $casts = [
        'is_export' => 'boolean',
        'is_fallback' => 'boolean',
    ];

    public function keywords(): HasMany
    {
        return $this->hasMany(ClassificationKeyword::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
