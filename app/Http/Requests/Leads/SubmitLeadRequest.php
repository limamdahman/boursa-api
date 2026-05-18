<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Support\Helpers\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class SubmitLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:message,call_click,whatsapp_click'],
            'message' => ['nullable', 'string', 'max:2000', 'required_if:type,message'],
            'sender_name' => ['nullable', 'string', 'max:150'],
            'sender_phone' => ['nullable', 'string', 'max:30'],
            'event_id' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function normalizedPhone(): ?string
    {
        $phone = $this->input('sender_phone');

        return is_string($phone) && $phone !== ''
            ? PhoneNormalizer::normalize($phone)
            : null;
    }
}
