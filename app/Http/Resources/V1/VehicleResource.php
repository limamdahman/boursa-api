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
            'price_mru'      => $this->price_mru,
            'original_price' => $this->original_price,
            'is_deal'        => (bool) $this->is_deal,
            'price_negotiable' => $this->price_negotiable,
            'price_rating' => $this->price_rating?->value,
            'price_rating_label' => $this->price_rating?->label(),
            'price_rating_label_ar' => $this->price_rating?->label('ar'),
            'price_rating_color' => $this->price_rating?->color(),
            'price_rating_description' => $this->price_rating?->description(),
            'price_rating_description_ar' => $this->price_rating?->description('ar'),
            'price_score' => $this->price_score ? (float) $this->price_score : null,
            'price_benchmark' => $this->price_benchmark_data,
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
            'agency' => $this->whenLoaded('agency', function () {
                $a = $this->agency;
                if (! $a) return null;

                // Extract lat/lng from PostGIS Point (WKB hex)
                $lat = null;
                $lng = null;
                if ($a->location && is_string($a->location) && strlen($a->location) >= 50) {
                    try {
                        $bin = hex2bin($a->location);
                        $lng = unpack('d', substr($bin, 9, 8))[1];
                        $lat = unpack('d', substr($bin, 17, 8))[1];
                    } catch (\Throwable $e) {
                        // ignore parse errors
                    }
                }

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'slug' => $a->slug,
                    'logo_url' => $a->logo_url,
                    'banner_url' => $a->banner_url,
                    'description' => $a->description,
                    'address' => $a->address,
                    'email' => $a->email,
                    'website' => $a->website,
                    'phone_whatsapp' => $a->phone_whatsapp,
                    'phone_call' => $a->phone_call,
                    'is_verified' => $a->isVerified() ?? false,
                    'verified_at' => $a->verified_at?->toIso8601String(),
                    'subscription_tier' => $a->subscription_tier,
                    'city' => $a->relationLoaded('city') && $a->city ? [
                        'id' => $a->city->id,
                        'name_fr' => $a->city->name_fr,
                        'name_ar' => $a->city->name_ar,
                    ] : null,
                    'lat' => $lat,
                    'lng' => $lng,
                    'vehicles_count' => $a->vehicles()->where('status', 'active')->count(),
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                $u = $this->user;
                if (! $u || $this->agency_id !== null) return null;
                // Particulier : on expose le nom + un téléphone masqué partiellement (pas exposer full pour la home)
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar_url' => $u->avatar_url,
                    'phone_whatsapp' => $u->phone,
                    'phone_call' => $u->phone,
                    'is_individual' => true,
                ];
            }),
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
