<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class ReorderVehicleMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAgency();
    }

    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'uuid'],
        ];
    }
}
