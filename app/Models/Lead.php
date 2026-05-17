<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'vehicle_id',
        'agency_id',
        'sender_user_id',
        'sender_name',
        'sender_phone',
        'type',
        'message',
        'is_read',
        'read_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'type' => LeadType::class,
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
