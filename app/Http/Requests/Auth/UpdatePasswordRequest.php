<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required_if:has_password,true', 'string', 'min:6'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }
}
