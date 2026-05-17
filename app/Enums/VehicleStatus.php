<?php

declare(strict_types=1);

namespace App\Enums;

enum VehicleStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SOLD = 'sold';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
}
