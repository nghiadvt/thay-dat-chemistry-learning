<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'created_by');
    }

    public function hostedSessions(): HasMany
    {
        return $this->hasMany(GameSession::class, 'host_id');
    }

    public function isTeacher(): bool
    {
        return in_array($this->role, ['teacher', 'admin'], true);
    }
}
