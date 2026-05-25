<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Évaluation du prix d'un véhicule par rapport à des véhicules similaires.
 * Style mobile.de Preisbewertung.
 */
enum PriceRating: string
{
    case VERY_GOOD = 'very_good';  // < 70% médiane
    case GOOD = 'good';             // 70-85%
    case FAIR = 'fair';             // 85-115%
    case HIGH = 'high';             // 115-130%
    case VERY_HIGH = 'very_high';   // > 130%

    public function label(string $lang = 'fr'): string
    {
        if ($lang === 'ar') {
            return match ($this) {
                self::VERY_GOOD => 'سعر ممتاز',
                self::GOOD => 'سعر جيد',
                self::FAIR => 'سعر عادل',
                self::HIGH => 'سعر مرتفع',
                self::VERY_HIGH => 'سعر مرتفع جداً',
            };
        }

        return match ($this) {
            self::VERY_GOOD => 'Très bon prix',
            self::GOOD => 'Bon prix',
            self::FAIR => 'Prix juste',
            self::HIGH => 'Prix élevé',
            self::VERY_HIGH => 'Prix très élevé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::VERY_GOOD => '#16A34A',   // vert Boursa
            self::GOOD => '#84CC16',         // vert clair
            self::FAIR => '#EAB308',         // jaune
            self::HIGH => '#F97316',         // orange
            self::VERY_HIGH => '#DC2626',    // rouge
        };
    }

    public function description(string $lang = 'fr'): string
    {
        if ($lang === 'ar') {
            return match ($this) {
                self::VERY_GOOD => 'هذا السعر أقل بكثير من معدل السوق',
                self::GOOD => 'هذا السعر أقل من معدل السوق',
                self::FAIR => 'هذا السعر يتوافق مع معدل السوق',
                self::HIGH => 'هذا السعر أعلى من معدل السوق',
                self::VERY_HIGH => 'هذا السعر أعلى بكثير من معدل السوق',
            };
        }

        return match ($this) {
            self::VERY_GOOD => 'Ce prix est nettement inférieur au prix du marché',
            self::GOOD => 'Ce prix est inférieur au prix du marché',
            self::FAIR => 'Ce prix correspond au prix du marché',
            self::HIGH => 'Ce prix est supérieur au prix du marché',
            self::VERY_HIGH => 'Ce prix est nettement supérieur au prix du marché',
        };
    }
}
