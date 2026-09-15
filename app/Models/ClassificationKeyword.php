<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationKeyword extends Model
{
    protected $fillable = ['client_category_id', 'keyword', 'priorite', 'actif'];

    protected $casts = [
        'priorite' => 'integer',
        'actif' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class, 'client_category_id');
    }
}
