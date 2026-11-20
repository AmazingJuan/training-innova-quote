<?php

namespace App\Http\Requests\Admin;

use App\Models\BusinessUnit;
use App\Utils\NameNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminBusinessUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The slug (`name`) is derived from the display name: it is the public URL
     * segment of the quotation flow, so it must be unique as well.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'abbreviation' => strtoupper(preg_replace('/\s+/', '', (string) $this->input('abbreviation', ''))),
            'name' => NameNormalizer::normalize((string) $this->input('display_name', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('businessUnit');

        return [
            'display_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', Rule::unique(BusinessUnit::class)->ignore($current)],
            'abbreviation' => ['required', 'string', 'min:2', 'max:32', 'regex:/^[A-Z0-9]+$/', Rule::unique(BusinessUnit::class)->ignore($current)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.required' => 'El nombre es obligatorio.',
            'display_name.string' => 'El nombre debe ser un texto.',
            'display_name.max' => 'El nombre no puede superar los 255 caracteres.',
            'name.required' => 'El nombre debe contener al menos una letra o número.',
            'name.unique' => 'Ya existe una unidad de negocio con un nombre equivalente.',
            'abbreviation.required' => 'El abreviado es obligatorio.',
            'abbreviation.min' => 'Use al menos 2 caracteres en el abreviado.',
            'abbreviation.max' => 'El abreviado no puede superar los 32 caracteres.',
            'abbreviation.regex' => 'El abreviado solo puede incluir letras mayúsculas y números, sin espacios.',
            'abbreviation.unique' => 'Ese abreviado ya está en uso por otra unidad de negocio.',
        ];
    }

    /**
     * `name` is not a form field: surface its errors under `display_name`.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        foreach ($errors->get('name') as $message) {
            $errors->add('display_name', $message);
        }

        parent::failedValidation($validator);
    }
}
