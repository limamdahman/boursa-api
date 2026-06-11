<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'phone',
        'firebase_uid',
        'status',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];
}
