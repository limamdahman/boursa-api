<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'user_id',
        'agency_id',
        'brand_id',
        'vehicle_model_id',
        'year',
        'mileage_km',
        'price_mru',
        'price_negotiable',
        'currency',
        'fuel',
        'transmission',
        'body_type',
        'color',
        'condition',
        'description_fr',
        'description_ar',
        'city_id',
        'specs',
        'status',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'price_negotiable' => 'boolean',
            'specs' => 'array',
            'status' => VehicleStatus::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(VehicleMedia::class)->orderBy('sort_order');
    }

    public function coverMedia()
    {
        return $this->hasOne(VehicleMedia::class)->where('is_cover', true);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VehicleStatus::ACTIVE->value);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeNearby(Builder $query, float $lat, float $lng, int $radiusMeters): Builder
    {
        return $query
            ->selectRaw('vehicles.*, ST_Distance(vehicles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) / 1000 AS distance_km', [$lng, $lat])
            ->whereRaw('ST_DWithin(vehicles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radiusMeters])
            ->orderByRaw('ST_Distance(vehicles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)', [$lng, $lat]);
    }
}
