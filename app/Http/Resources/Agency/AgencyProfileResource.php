<?php

declare(strict_types=1);

namespace App\Http\Resources\Agency;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agency
 */
class AgencyProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name_fr' => $this->city->name_fr,
            ] : null),
            'rc_number' => $this->rc_number,
            'phone_whatsapp' => $this->phone_whatsapp,
            'phone_call' => $this->phone_call,
            'email' => $this->email,
            'website' => $this->website,
            'status' => $this->status,
            'is_verified' => $this->isVerified(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'subscription_tier' => $this->subscription_tier,
            'quota_active_listings' => $this->quota_active_listings,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
