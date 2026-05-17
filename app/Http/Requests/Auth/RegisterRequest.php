<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\Helpers\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'language' => ['nullable', 'in:fr,ar'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function normalizedPhone(): ?string
    {
        return PhoneNormalizer::normalize((string) $this->input('phone'));
    }
}
