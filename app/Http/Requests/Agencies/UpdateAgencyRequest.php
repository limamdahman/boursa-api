<?php

declare(strict_types=1);

namespace App\Http\Requests\Agencies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAgency();
    }

    public function rules(): array
    {
        $agencyId = $this->user()->agency?->id;

        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:500'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'rc_number' => ['nullable', 'string', 'max:50'],
            'phone_whatsapp' => ['nullable', 'string', 'max:20'],
            'phone_call' => ['nullable', 'string', 'max:20'],
            'email' => [
                'nullable', 'email', 'max:150',
                Rule::unique('agencies', 'email')->ignore($agencyId),
            ],
            'website' => ['nullable', 'url', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
