<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by create and update. On update the password is optional: when it
 * comes empty it is excluded so the current hash is kept.
 */
class AdminUserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'password' => $user === null
                ? ['required', 'string', 'min:8']
                : [Rule::excludeIf($this->input('password') === null), 'string', 'min:8'],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio',
            'name.max' => 'El nombre no puede superar los 255 caracteres',
            'email.required' => 'El correo es obligatorio',
            'email.email' => 'El correo debe ser válido',
            'email.unique' => 'Este correo ya está registrado',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'phone.required' => 'El teléfono es obligatorio',
            'phone.string' => 'El teléfono debe ser un texto válido',
            'phone.min' => 'El teléfono debe tener al menos 10 dígitos',
            'phone.max' => 'El teléfono no puede tener más de 15 dígitos',
        ];
    }
}
