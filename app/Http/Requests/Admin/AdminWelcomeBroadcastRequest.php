<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AdminWelcomeBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'emails_text' => ['required', 'string', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'emails_text.required' => 'Indique al menos una dirección de correo electrónico.',
            'emails_text.max' => 'El texto no puede superar los :max caracteres.',
        ];
    }
}
