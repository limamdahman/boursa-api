<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
                'logo_url' => $this->brand->logo_url,
            ]),
            'model' => $this->whenLoaded('vehicleModel', fn () => [
                'id' => $this->vehicleModel->id,
                'name' => $this->vehicleModel->name,
                'slug' => $this->vehicleModel->slug,
            ]),
            'year' => $this->year,
            'mileage_km' => $this->mileage_km,
            'price_mru' => $this->price_mru,
            'price_negotiable' => $this->price_negotiable,
            'currency' => $this->currency,
            'fuel' => $this->fuel,
            'transmission' => $this->transmission,
            'body_type' => $this->body_type,
            'color' => $this->color,
            'condition' => $this->condition,
            'description_fr' => $this->description_fr,
            'description_ar' => $this->description_ar,
            'specs' => $this->specs,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name_fr' => $this->city?->name_fr,
                'name_ar' => $this->city?->name_ar,
                'region' => $this->city?->region,
            ]),
            'agency' => $this->whenLoaded('agency', fn () => [
                'id' => $this->agency?->id,
                'name' => $this->agency?->name,
                'slug' => $this->agency?->slug,
                'logo_url' => $this->agency?->logo_url,
                'phone_whatsapp' => $this->agency?->phone_whatsapp,
                'phone_call' => $this->agency?->phone_call,
                'is_verified' => $this->agency?->isVerified() ?? false,
            ]),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'url_thumb' => $m->url_thumb,
                'url_md' => $m->url_webp_md,
                'url_lg' => $m->url_webp_lg,
                'is_cover' => $m->is_cover,
            ])),
            'stats' => [
                'views_count' => $this->views_count,
                'contacts_count' => $this->contacts_count,
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
