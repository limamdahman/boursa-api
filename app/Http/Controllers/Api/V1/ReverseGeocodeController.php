<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReverseGeocodeController extends Controller
{
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'lang' => ['sometimes', 'in:fr,ar,en'],
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $lang = $validated['lang'] ?? 'fr';

        // Cache 24h pour éviter de spammer Nominatim
        $cacheKey = 'reverse_geocode:' . round($lat, 4) . ',' . round($lng, 4) . ':' . $lang;

        $result = Cache::remember($cacheKey, now()->addHours(24), function () use ($lat, $lng, $lang) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'BoursaApp/1.0 (contact@boursa.mr)',
                ])->timeout(5)->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lng,
                    'accept-language' => $lang,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

                if (! $response->successful()) {
                    return null;
                }

                return $response->json();
            } catch (\Throwable $e) {
                return null;
            }
        });

        if (! $result) {
            return response()->json(['success' => false, 'message' => 'Reverse geocoding indisponible.'], 503);
        }

        $address = $result['address'] ?? [];

        // Construire adresse courte
        $parts = [];
        if (! empty($address['road'])) $parts[] = $address['road'];
        elseif (! empty($address['pedestrian'])) $parts[] = $address['pedestrian'];
        if (! empty($address['suburb']) || ! empty($address['neighbourhood']) || ! empty($address['quarter'])) {
            $parts[] = $address['suburb'] ?? $address['neighbourhood'] ?? $address['quarter'];
        }
        $shortAddress = ! empty($parts)
            ? implode(', ', $parts)
            : implode(',', array_slice(explode(',', $result['display_name'] ?? ''), 0, 2));

        // Identifier la ville BDD : prioriser county (moughataa) > city > state
        $cityCandidates = array_filter([
            $address['county'] ?? null,
            $address['city'] ?? null,
            $address['state_district'] ?? null,
            $address['suburb'] ?? null,
        ]);

        $cityId = null;
        $cityName = null;
        foreach ($cityCandidates as $candidate) {
            $city = City::query()
                ->where('name_fr', 'ILIKE', $candidate)
                ->orWhere('name_ar', 'LIKE', $candidate)
                ->first();
            if ($city) {
                $cityId = $city->id;
                $cityName = $city->name_fr;
                break;
            }
        }

        // Fallback fuzzy : si pas trouvé, partial match
        if (! $cityId) {
            foreach ($cityCandidates as $candidate) {
                $city = City::query()
                    ->where('name_fr', 'ILIKE', '%' . $candidate . '%')
                    ->first();
                if ($city) {
                    $cityId = $city->id;
                    $cityName = $city->name_fr;
                    break;
                }
            }
        }

        return response()->json([
            'success' => true,
            'address' => trim($shortAddress, ' ,'),
            'city_id' => $cityId,
            'city_name' => $cityName,
            'display_name' => $result['display_name'] ?? null,
            'raw' => $address,
        ]);
    }
}
