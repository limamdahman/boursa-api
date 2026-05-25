<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['sometimes', 'integer', 'exists:brands,id'],
            'vehicle_model_id' => ['sometimes', 'integer', 'exists:vehicle_models,id'],
            'year' => ['sometimes', 'integer', 'min:1980', 'max:2030'],
            'mileage_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'price_mru' => ['sometimes', 'integer', 'min:50000'],
            'price_negotiable' => ['nullable', 'boolean'],
            'fuel' => ['nullable', 'in:gasoline,diesel,hybrid,electric,lpg'],
            'transmission' => ['nullable', 'in:manual,automatic'],
            'body_type' => ['nullable', 'in:sedan,suv,pickup,hatchback,van,coupe'],
            'color' => ['nullable', 'string', 'max:30'],
            'condition' => ['nullable', 'in:new,used,imported'],
            'description_fr' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'specs' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
