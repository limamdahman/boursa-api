<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agency
 */
class AgencyListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'banner_url' => $this->banner_url,
            'address' => $this->address,
            'is_verified' => $this->isVerified() ?? false,
            'subscription_tier' => $this->subscription_tier,
            'vehicles_count' => $this->vehicles_count ?? $this->vehicles()->where('status', 'active')->count(),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name_fr' => $this->city?->name_fr,
                'name_ar' => $this->city?->name_ar,
            ]),
        ];
    }
}
