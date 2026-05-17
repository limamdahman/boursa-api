<?php

declare(strict_types=1);

namespace App\Enums;

enum AgencyStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case SUSPENDED = 'suspended';
}
