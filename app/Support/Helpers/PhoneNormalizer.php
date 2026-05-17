<?php

declare(strict_types=1);

namespace App\Support\Helpers;

final class PhoneNormalizer
{
    /**
     * Normalise un numéro mauritanien au format E.164 sans le '+'.
     * Mauritanie : indicatif 222, numéros à 8 chiffres (mobile commence par 2, 3, 4).
     *
     * Exemples acceptés :
     *  - "+222 44 55 66 77" → "22244556677"
     *  - "00222 44 55 66 77" → "22244556677"
     *  - "44 55 66 77"      → "22244556677"
     *  - "44556677"         → "22244556677"
     */
    public static function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Format 00222XXXXXXXX → 222XXXXXXXX
        if (str_starts_with($digits, '00222')) {
            $digits = substr($digits, 2);
        }

        // Format 222XXXXXXXX (11 chiffres) déjà bon
        if (strlen($digits) === 11 && str_starts_with($digits, '222')) {
            return $digits;
        }

        // Format local 8 chiffres → ajouter 222
        if (strlen($digits) === 8 && in_array($digits[0], ['2', '3', '4'], true)) {
            return '222'.$digits;
        }

        return null;
    }

    public static function isValid(string $phone): bool
    {
        return self::normalize($phone) !== null;
    }
}
