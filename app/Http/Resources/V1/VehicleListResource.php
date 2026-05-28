<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
            ]),
            'model' => $this->whenLoaded('vehicleModel', fn () => [
                'id' => $this->vehicleModel->id,
                'name' => $this->vehicleModel->name,
            ]),
            'year' => $this->year,
            'mileage_km' => $this->mileage_km,
            'price_mru' => $this->price_mru,
            'original_price' => $this->original_price,
            'is_deal' => (bool) $this->is_deal,
            'price_negotiable' => $this->price_negotiable,
            'fuel' => $this->fuel,
            'transmission' => $this->transmission,
            'body_type' => $this->body_type,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name_fr' => $this->city?->name_fr,
                'name_ar' => $this->city?->name_ar,
            ]),
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn () => round((float) $this->distance_km, 2)
            ),
            'cover_image' => $this->whenLoaded('coverMedia', fn () => $this->coverMedia?->url_webp_md),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
