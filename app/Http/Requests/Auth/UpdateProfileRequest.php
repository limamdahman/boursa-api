<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'language' => ['sometimes', 'in:fr,ar,en'],
            'locale' => ['sometimes', 'in:fr,ar,en'],
        ];
    }
}
