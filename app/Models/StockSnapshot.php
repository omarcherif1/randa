<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSnapshot extends Model
{
    protected $fillable = ['article', 'produit_id', 'date_stock', 'quantite_kg', 'import_batch_id'];

    protected $casts = [
        'date_stock' => 'date',
        'quantite_kg' => 'float',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
