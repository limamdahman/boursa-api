<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadType: string
{
    case MESSAGE = 'message';
    case CALL_CLICK = 'call_click';
    case WHATSAPP_CLICK = 'whatsapp_click';
}
