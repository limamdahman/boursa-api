<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMedia extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'vehicle_media';

    protected $fillable = [
        'vehicle_id',
        'url_original',
        'url_webp_lg',
        'url_webp_md',
        'url_thumb',
        'sort_order',
        'is_cover',
        'watermarked',
        'width',
        'height',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'watermarked' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
