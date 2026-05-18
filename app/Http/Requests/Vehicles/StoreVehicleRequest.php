<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAgency();
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'vehicle_model_id' => ['required', 'integer', 'exists:vehicle_models,id'],
            'year' => ['required', 'integer', 'min:1980', 'max:2030'],
            'mileage_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'price_mru' => ['required', 'integer', 'min:50000'],
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
            'specs.options' => ['nullable', 'array'],
            'specs.options.*' => ['string', 'max:50'],
            'specs.doors' => ['nullable', 'integer', 'min:2', 'max:7'],
            'specs.seats' => ['nullable', 'integer', 'min:2', 'max:15'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
