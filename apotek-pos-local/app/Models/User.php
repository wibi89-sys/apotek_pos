<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUlids, Notifiable;

    protected $fillable = [
        'branch_id',
        'name',
        'username',
        'email',
        'password',
        'role',
        'pin_hash',
        'fingerprint_template',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pin_hash',
        'fingerprint_template',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER;
    }

    public function isApoteker(): bool
    {
        return $this->role === UserRole::APOTEKER;
    }

    public function isSupervisor(): bool
    {
        return in_array($this->role, [UserRole::OWNER, UserRole::APOTEKER], true);
    }
}
