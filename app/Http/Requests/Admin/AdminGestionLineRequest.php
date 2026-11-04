<?php

namespace App\Http\Requests\Admin;

use App\Models\GestionLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminGestionLineRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique(GestionLine::class)->ignore($this->route('gestionLine'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser un texto.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'name.unique' => 'Ya existe una línea de gestión con ese nombre.',
        ];
    }
}
