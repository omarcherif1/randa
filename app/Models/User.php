<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'is_admin', 'locked_at', 'locked_reason', 'must_change_password',
        'failed_login_attempts', 'locked_until', 'last_login_at', 'created_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'locked_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function societes(): BelongsToMany
    {
        return $this->belongsToMany(Societe::class);
    }

    public function pagePermissions(): HasMany
    {
        return $this->hasMany(PagePermission::class);
    }

    /** Règle 3.1 — compte verrouillé : aucun accès, même en pleine session. */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** @return list<int> */
    public function societeIds(): array
    {
        return $this->societes()->pluck('societes.id')->all();
    }
}
