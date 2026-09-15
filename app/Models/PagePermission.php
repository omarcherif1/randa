<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagePermission extends Model
{
    protected $fillable = ['user_id', 'page_key', 'granted_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
