<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case AGENCY = 'agency';
    case USER = 'user';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::AGENCY => 'Agence',
            self::USER => 'Utilisateur',
        };
    }
}
