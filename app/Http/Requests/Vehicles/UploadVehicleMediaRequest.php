<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class UploadVehicleMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required', 'file', 'image',
                'mimes:jpg,jpeg,png,webp',
                'max:15360',
            ],
            'is_cover' => ['nullable', 'boolean'],
        ];
    }
}
