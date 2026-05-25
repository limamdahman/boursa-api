<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerFollow extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'follower_user_id', 'seller_user_id', 'seller_agency_id',
    ];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_user_id');
    }

    public function sellerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function sellerAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'seller_agency_id');
    }
}
