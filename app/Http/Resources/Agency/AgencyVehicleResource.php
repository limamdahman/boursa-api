<?php

declare(strict_types=1);

namespace App\Http\Resources\Agency;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class AgencyVehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ]),
            'model' => $this->whenLoaded('vehicleModel', fn () => [
                'id' => $this->vehicleModel->id,
                'name' => $this->vehicleModel->name,
            ]),
            'year' => $this->year,
            'mileage_km' => $this->mileage_km,
            'price_mru' => $this->price_mru,
            'price_negotiable' => $this->price_negotiable,
            'fuel' => $this->fuel,
            'transmission' => $this->transmission,
            'body_type' => $this->body_type,
            'color' => $this->color,
            'condition' => $this->condition,
            'description_fr' => $this->description_fr,
            'description_ar' => $this->description_ar,
            'specs' => $this->specs,
            'status' => $this->status,
            'moderation_notes' => $this->moderation_notes,
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'url_thumb' => $m->url_thumb,
                'url_md' => $m->url_webp_md,
                'url_original' => $m->url_original,
                'is_cover' => $m->is_cover,
                'watermarked' => $m->watermarked,
                'sort_order' => $m->sort_order,
            ])),
            'stats' => [
                'views_count' => $this->views_count,
                'contacts_count' => $this->contacts_count,
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
