<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Brand;
use App\Models\City;
use Illuminate\Support\Str;

/**
 * Parser de requêtes en langage naturel pour la recherche de véhicules.
 * Support FR + AR.
 *
 * Exemples :
 *  - "SUV moins de 2M MRU" → ['body_type' => 'suv', 'price_max' => 2000000]
 *  - "Toyota Camry diesel" → ['brand_id' => 1, 'fuel' => 'diesel']
 *  - "Pickup automatique Nouakchott" → ['body_type' => 'pickup', 'transmission' => 'automatic', 'city_id' => 1]
 *  - "سيارة دفع رباعي أقل من 2 مليون" → ['body_type' => 'suv', 'price_max' => 2000000]
 */
final class SearchQueryParser
{
    private array $brands = [];

    private array $cities = [];

    public function __construct()
    {
        $this->brands = Brand::all(['id', 'name', 'slug'])->toArray();
        $this->cities = City::all(['id', 'name_fr', 'name_ar'])->toArray();
    }

    /**
     * Parse une requête en langage naturel et retourne un tableau de filtres.
     * Garde aussi le texte résiduel non parsé pour un fallback full-text.
     *
     * @return array{filters: array<string,mixed>, fulltext: string|null, parsed_chips: array<int,array{key:string,label:string,value:mixed}>}
     */
    public function parse(string $query, string $lang = 'fr'): array
    {
        $original = trim($query);
        if ($original === '') {
            return ['filters' => [], 'fulltext' => null, 'parsed_chips' => []];
        }

        $q = mb_strtolower($original);
        $filters = [];
        $chips = [];
        $consumedRanges = [];

        // 1. Prix (mu = MRU, M = millions)
        $priceMax = $this->extractPriceMax($q, $consumedRanges);
        if ($priceMax !== null) {
            $filters['price_max'] = $priceMax['value'];
            $chips[] = ['key' => 'price_max', 'label' => $priceMax['label'], 'value' => $priceMax['value']];
        }

        $priceMin = $this->extractPriceMin($q, $consumedRanges);
        if ($priceMin !== null) {
            $filters['price_min'] = $priceMin['value'];
            $chips[] = ['key' => 'price_min', 'label' => $priceMin['label'], 'value' => $priceMin['value']];
        }

        // 2. Année
        $yearMin = $this->extractYearMin($q, $consumedRanges);
        if ($yearMin !== null) {
            $filters['year_min'] = $yearMin['value'];
            $chips[] = ['key' => 'year_min', 'label' => $yearMin['label'], 'value' => $yearMin['value']];
        }

        // 3. Km max
        $kmMax = $this->extractKmMax($q, $consumedRanges);
        if ($kmMax !== null) {
            $filters['mileage_max'] = $kmMax['value'];
            $chips[] = ['key' => 'mileage_max', 'label' => $kmMax['label'], 'value' => $kmMax['value']];
        }

        // 4. Body type
        $body = $this->extractBodyType($q);
        if ($body !== null) {
            $filters['body_type'] = $body['value'];
            $chips[] = ['key' => 'body_type', 'label' => $body['label'], 'value' => $body['value']];
        }

        // 5. Fuel
        $fuel = $this->extractFuel($q);
        if ($fuel !== null) {
            $filters['fuel'] = $fuel['value'];
            $chips[] = ['key' => 'fuel', 'label' => $fuel['label'], 'value' => $fuel['value']];
        }

        // 6. Transmission
        $transmission = $this->extractTransmission($q);
        if ($transmission !== null) {
            $filters['transmission'] = $transmission['value'];
            $chips[] = ['key' => 'transmission', 'label' => $transmission['label'], 'value' => $transmission['value']];
        }

        // 7. Condition
        $condition = $this->extractCondition($q);
        if ($condition !== null) {
            $filters['condition'] = $condition['value'];
            $chips[] = ['key' => 'condition', 'label' => $condition['label'], 'value' => $condition['value']];
        }

        // 8. Brand
        $brand = $this->extractBrand($q);
        if ($brand !== null) {
            $filters['brand_id'] = $brand['value'];
            $chips[] = ['key' => 'brand_id', 'label' => $brand['label'], 'value' => $brand['value']];
        }

        // 9. City
        $city = $this->extractCity($q);
        if ($city !== null) {
            $filters['city_id'] = $city['value'];
            $chips[] = ['key' => 'city_id', 'label' => $city['label'], 'value' => $city['value']];
        }

        // Reste = texte pour fulltext fallback
        $fulltext = empty($filters) ? $original : null;

        return [
            'filters' => $filters,
            'fulltext' => $fulltext,
            'parsed_chips' => $chips,
        ];
    }

    private function extractPriceMax(string $q, array &$consumed): ?array
    {
        // FR : "moins de 2 millions", "moins de 2M", "< 2M MRU", "max 2M"
        // AR : "أقل من 2 مليون", "حتى 2 مليون"
        $patterns = [
            '/(?:moins de|moins que|< ?|inf[ée]rieur (?:[àa])|max(?:imum)?|au plus|jusqu[\'’]?(?:[àa])|أقل من|حتى|لا يزيد عن)\s*(\d+(?:[.,]\d+)?)\s*(m|millions?|million|m mru|mil|k|mille|mru|أوقية|مليون|ألف)\b/iu',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $q, $m)) {
                $value = $this->parseAmount($m[1], $m[2]);
                if ($value > 0) {
                    return [
                        'value' => $value,
                        'label' => 'Prix max ' . $this->fmtAmount($value),
                    ];
                }
            }
        }

        return null;
    }

    private function extractPriceMin(string $q, array &$consumed): ?array
    {
        $patterns = [
            '/(?:plus de|> ?|sup[ée]rieur (?:[àa])|min(?:imum)?|au moins|[àa] partir de|أكثر من|على الأقل|ابتداء من)\s*(\d+(?:[.,]\d+)?)\s*(m|millions?|million|m mru|mil|k|mille|mru|أوقية|مليون|ألف)\b/iu',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $q, $m)) {
                $value = $this->parseAmount($m[1], $m[2]);
                if ($value > 0) {
                    return [
                        'value' => $value,
                        'label' => 'Prix min ' . $this->fmtAmount($value),
                    ];
                }
            }
        }

        return null;
    }

    private function extractYearMin(string $q, array &$consumed): ?array
    {
        $patterns = [
            '/(?:depuis|[àa] partir de|min(?:imum)?|≥|>=|>|ann[ée]e min|من|ابتداء من|سنة)\s*(\d{4})\b/iu',
            '/\b(\d{4})\s*(?:ou plus|et plus|\+|أو أحدث|أو أكثر)\b/iu',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $q, $m)) {
                $year = (int) $m[1];
                if ($year >= 1980 && $year <= 2030) {
                    return ['value' => $year, 'label' => 'Année ≥ ' . $year];
                }
            }
        }

        return null;
    }

    private function extractKmMax(string $q, array &$consumed): ?array
    {
        $patterns = [
            '/(?:moins de|< ?|max(?:imum)?|jusqu[\'’]?(?:[àa])|أقل من|حتى)\s*(\d+(?:[.,]\d+)?)\s*(k|km|kilom[èe]tres?|كم|كيلومتر)\b/iu',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $q, $m)) {
                $num = (float) str_replace(',', '.', $m[1]);
                $unit = mb_strtolower($m[2]);

                if (in_array($unit, ['k'])) {
                    $value = (int) ($num * 1000);
                } else {
                    $value = (int) $num;
                }

                if ($value > 0 && $value <= 1000000) {
                    return [
                        'value' => $value,
                        'label' => 'Km max ' . number_format($value, 0, ',', ' '),
                    ];
                }
            }
        }

        return null;
    }

    private function extractBodyType(string $q): ?array
    {
        $map = [
            'suv' => ['suv', 'tout-terrain', 'tout terrain', 'دفع رباعي', 'سيارة دفع رباعي'],
            'sedan' => ['berline', 'sedan', 'سيدان'],
            'pickup' => ['pickup', 'pick-up', 'pick up', 'بيك أب', 'بيكاب'],
            'hatchback' => ['compacte', 'hatchback', 'مدمجة', 'كومباكت'],
            'van' => ['utilitaire', 'van', 'فان', 'يوتيلتي'],
            'coupe' => ['coupé', 'coupe', 'كوبيه'],
        ];

        $labels = [
            'suv' => 'SUV', 'sedan' => 'Berline', 'pickup' => 'Pickup',
            'hatchback' => 'Compacte', 'van' => 'Utilitaire', 'coupe' => 'Coupé',
        ];

        foreach ($map as $value => $terms) {
            foreach ($terms as $term) {
                if (Str::contains($q, mb_strtolower($term))) {
                    return ['value' => $value, 'label' => $labels[$value]];
                }
            }
        }

        return null;
    }

    private function extractFuel(string $q): ?array
    {
        $map = [
            'diesel' => ['diesel', 'gazole', 'ديزل', 'مازوت'],
            'gasoline' => ['essence', 'gasoline', 'بنزين'],
            'hybrid' => ['hybride', 'hybrid', 'هايبرد', 'هجين'],
            'electric' => ['électrique', 'electrique', 'electric', 'كهربائي', 'كهرباء'],
            'lpg' => ['gpl', 'gaz', 'lpg', 'غاز'],
        ];

        $labels = [
            'diesel' => 'Diesel', 'gasoline' => 'Essence', 'hybrid' => 'Hybride',
            'electric' => 'Électrique', 'lpg' => 'GPL',
        ];

        foreach ($map as $value => $terms) {
            foreach ($terms as $term) {
                if (Str::contains($q, mb_strtolower($term))) {
                    return ['value' => $value, 'label' => $labels[$value]];
                }
            }
        }

        return null;
    }

    private function extractTransmission(string $q): ?array
    {
        $map = [
            'automatic' => ['automatique', 'auto', 'automatic', 'أوتوماتيكي', 'أوتو'],
            'manual' => ['manuelle', 'manuel', 'manual', 'يدوي', 'يدوية'],
        ];

        $labels = ['automatic' => 'Automatique', 'manual' => 'Manuelle'];

        foreach ($map as $value => $terms) {
            foreach ($terms as $term) {
                if (Str::contains($q, mb_strtolower($term))) {
                    return ['value' => $value, 'label' => $labels[$value]];
                }
            }
        }

        return null;
    }

    private function extractCondition(string $q): ?array
    {
        $map = [
            'new' => ['neuf', 'neuve', 'nouveau', 'nouvelle', 'new', 'جديد', 'جديدة'],
            'used' => ['occasion', 'used', 'مستعمل', 'مستعملة'],
            'imported' => ['importé', 'importée', 'import', 'مستورد', 'مستوردة'],
        ];

        $labels = ['new' => 'Neuf', 'used' => 'Occasion', 'imported' => 'Importé'];

        foreach ($map as $value => $terms) {
            foreach ($terms as $term) {
                if (Str::contains($q, mb_strtolower($term))) {
                    return ['value' => $value, 'label' => $labels[$value]];
                }
            }
        }

        return null;
    }

    private function extractBrand(string $q): ?array
    {
        // Map AR for brands (parser ne peut pas devine "تويوتا" → Toyota)
        $arMap = [
            'تويوتا' => 'toyota', 'هيونداي' => 'hyundai', 'نيسان' => 'nissan',
            'كيا' => 'kia', 'مرسيدس' => 'mercedes-benz', 'ميتسوبيشي' => 'mitsubishi',
            'رينو' => 'renault', 'بيجو' => 'peugeot', 'فولكسفاجن' => 'volkswagen',
            'فورد' => 'ford', 'بي إم دبليو' => 'bmw', 'أودي' => 'audi',
            'لاند روفر' => 'land-rover', 'لكزس' => 'lexus', 'إيسوزو' => 'isuzu',
            'مازدا' => 'mazda', 'سوزوكي' => 'suzuki', 'هوندا' => 'honda',
            'شيري' => 'chery', 'جيلي' => 'geely', 'بي واي دي' => 'byd',
            'فيات' => 'fiat', 'سيتروين' => 'citroen', 'أوبل' => 'opel',
            'داشيا' => 'dacia',
        ];

        // Cherche AR d'abord
        foreach ($arMap as $arName => $slug) {
            if (Str::contains($q, mb_strtolower($arName))) {
                $brand = collect($this->brands)->firstWhere('slug', $slug);
                if ($brand) {
                    return ['value' => $brand['id'], 'label' => $brand['name']];
                }
            }
        }

        // Cherche FR (par slug ou nom)
        foreach ($this->brands as $brand) {
            $nameLower = mb_strtolower($brand['name']);
            $slugLower = mb_strtolower($brand['slug']);
            if (Str::contains($q, $nameLower) || Str::contains($q, $slugLower)) {
                return ['value' => $brand['id'], 'label' => $brand['name']];
            }
        }

        return null;
    }

    private function extractCity(string $q): ?array
    {
        foreach ($this->cities as $city) {
            $nameFr = mb_strtolower($city['name_fr']);
            $nameAr = mb_strtolower($city['name_ar']);
            if (Str::contains($q, $nameFr) || Str::contains($q, $nameAr)) {
                return ['value' => $city['id'], 'label' => $city['name_fr']];
            }
        }

        return null;
    }

    /**
     * Parse un montant + unité en valeur entière MRU.
     */
    private function parseAmount(string $rawNum, string $unit): int
    {
        $num = (float) str_replace(',', '.', $rawNum);
        $unit = mb_strtolower($unit);

        $multiplier = match (true) {
            in_array($unit, ['m', 'million', 'millions', 'm mru', 'مليون']) => 1_000_000,
            in_array($unit, ['k', 'mil', 'mille', 'ألف']) => 1_000,
            in_array($unit, ['mru', 'أوقية']) => 1,
            default => 1,
        };

        return (int) ($num * $multiplier);
    }

    private function fmtAmount(int $value): string
    {
        if ($value >= 1_000_000) {
            return rtrim(rtrim(number_format($value / 1_000_000, 1, '.', ''), '0'), '.') . 'M MRU';
        }
        if ($value >= 1_000) {
            return number_format($value / 1_000, 0, '.', '') . 'K MRU';
        }

        return $value . ' MRU';
    }
}
