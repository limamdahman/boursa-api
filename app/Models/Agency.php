<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgencyStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'logo_url',
        'banner_url',
        'description',
        'address',
        'city_id',
        'rc_number',
        'phone_whatsapp',
        'phone_call',
        'email',
        'website',
        'status',
        'verified_at',
        'subscription_tier',
        'quota_active_listings',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'status' => AgencyStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function isVerified(): bool
    {
        return $this->status === AgencyStatus::VERIFIED;
    }

    /**
     * Transforme logo_url (path) en URL complète MinIO.
     */
    public function getLogoUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        // Si déjà une URL complète, retourner tel quel
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        // Sinon construire l'URL via Storage
        try {
            return \Illuminate\Support\Facades\Storage::disk('s3')->url($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Transforme banner_url (path) en URL complète MinIO.
     */
    public function getBannerUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        try {
            return \Illuminate\Support\Facades\Storage::disk('s3')->url($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

}
