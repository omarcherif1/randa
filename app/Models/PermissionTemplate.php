<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionTemplate extends Model
{
    protected $fillable = ['code', 'nom', 'description', 'accorde_admin', 'pages'];

    protected $casts = [
        'accorde_admin' => 'boolean',
        'pages' => 'array',
    ];
}
