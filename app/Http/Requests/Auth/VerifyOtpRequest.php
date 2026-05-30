<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\Helpers\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:150'],
            'code' => ['required', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function normalizedPhone(): ?string
    {
        return PhoneNormalizer::normalize((string) $this->input('phone'));
    }
}
