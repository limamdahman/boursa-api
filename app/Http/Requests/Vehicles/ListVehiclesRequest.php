<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class ListVehiclesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'vehicle_model_id' => ['nullable', 'integer', 'exists:vehicle_models,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'agency_id' => ['nullable', 'string', 'uuid', 'exists:agencies,id'],
            'exclude' => ['nullable', 'string', 'uuid'],
            'year_min' => ['nullable', 'integer', 'min:1980', 'max:2030'],
            'year_max' => ['nullable', 'integer', 'min:1980', 'max:2030'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'mileage_max' => ['nullable', 'integer', 'min:0'],
            'fuel' => ['nullable', 'in:gasoline,diesel,hybrid,electric,lpg'],
            'transmission' => ['nullable', 'in:manual,automatic'],
            'body_type' => ['nullable', 'in:sedan,suv,pickup,hatchback,van,coupe'],
            'condition' => ['nullable', 'in:new,used,imported'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'sort' => ['nullable', 'in:recent,price_asc,price_desc,year_desc,mileage_asc,distance'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
