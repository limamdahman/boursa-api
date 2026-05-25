<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

/**
 * @mixin Agency
 */
class AgencyDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Extract lat/lng from PostGIS Point (WKB hex)
        $lat = null;
        $lng = null;
        if ($this->location && is_string($this->location) && strlen($this->location) >= 50) {
            try {
                $bin = hex2bin($this->location);
                $lng = unpack('d', substr($bin, 9, 8))[1];
                $lat = unpack('d', substr($bin, 17, 8))[1];
            } catch (Throwable $e) {
                // ignore parse errors
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'banner_url' => $this->banner_url,
            'description' => $this->description,
            'address' => $this->address,
            'email' => $this->email,
            'website' => $this->website,
            'phone_whatsapp' => $this->phone_whatsapp,
            'phone_call' => $this->phone_call,
            'is_verified' => $this->isVerified() ?? false,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'subscription_tier' => $this->subscription_tier,
            'created_at' => $this->created_at?->toIso8601String(),
            'vehicles_count' => $this->vehicles()->where('status', 'active')->count(),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name_fr' => $this->city?->name_fr,
                'name_ar' => $this->city?->name_ar,
            ]),
            'lat' => $lat,
            'lng' => $lng,
        ];
    }
}
