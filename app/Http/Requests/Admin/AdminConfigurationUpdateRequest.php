<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminConfigurationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notification_email' => ['nullable', 'email', 'max:255'],
            'company_contact_email' => ['nullable', 'email', 'max:255'],
            'company_contact_phone' => ['nullable', 'string', 'max:64'],
            'company_contact_address' => ['nullable', 'string', 'max:2000'],
            'company_website_url' => ['nullable', 'url', 'max:512'],
            'company_website_label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notification_email.email' => 'El correo de notificaciones debe ser una dirección válida.',
            'notification_email.max' => 'El correo no puede superar los :max caracteres.',
            'company_contact_email.email' => 'El correo de contacto debe ser una dirección válida.',
            'company_contact_phone.max' => 'El teléfono no puede superar los :max caracteres.',
            'company_contact_address.max' => 'La dirección no puede superar los :max caracteres.',
            'company_website_url.url' => 'La URL del sitio web debe ser una dirección válida (https://…).',
            'company_website_url.max' => 'La URL del sitio web no puede superar los :max caracteres.',
        ];
    }
}
