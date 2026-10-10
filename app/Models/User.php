<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use \App\Models\Concerns\ProtectsRoomAreaSignatures;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function googlePhotosToken(): HasOne
    {
        return $this->hasOne(GooglePhotosToken::class);
    }

    /**
     * 'admin' (full access incl. deleting records) vs 'staff'
     * (day-to-day use, blocked from destructive actions). Unknown or
     * missing role is treated as staff.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
