<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminServiceRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique(Service::class)->ignore($this->route('service'))],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'gestion_line_id' => ['required', 'integer', 'exists:gestion_lines,id'],
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
            'name.unique' => 'Ya existe un servicio con ese nombre.',

            'business_unit_id.required' => 'La unidad de negocio es obligatoria.',
            'business_unit_id.exists' => 'La unidad de negocio seleccionada no es válida.',

            'gestion_line_id.required' => 'La línea de gestión es obligatoria.',
            'gestion_line_id.exists' => 'La línea de gestión seleccionada no es válida.',
        ];
    }
}
