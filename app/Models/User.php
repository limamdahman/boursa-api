<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'language',
        'avatar_url',
        'email_verified_at',
        'phone_verified_at',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function agency(): HasOne
    {
        return $this->hasOne(Agency::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    public function isAgency(): bool
    {
        return $this->role === UserRole::AGENCY;
    }

    /**
     * Restreint l'accès au panel Filament aux administrateurs uniquement.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Panel admin : réservé aux ADMIN uniquement
        if ($panel->getId() === 'admin') {
            return $this->role === UserRole::ADMIN;
        }

        // Panel agency : réservé aux AGENCY (avec une agence liée)
        if ($panel->getId() === 'agency') {
            return $this->role === UserRole::AGENCY && $this->agency()->exists();
        }

        return false;
    }
}
