<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'hourly_rate', 'flat_rate', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const TECHNICIAN_ROLE = 'technician';

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'hourly_rate' => 'decimal:2',
            'flat_rate' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isTechnician(): bool
    {
        return $this->hasRole(self::TECHNICIAN_ROLE);
    }
}
