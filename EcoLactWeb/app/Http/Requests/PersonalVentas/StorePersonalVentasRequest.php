<?php

namespace App\Http\Requests\PersonalVentas;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonalVentasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dni' => ['required', 'string', 'regex:/^\d{8}$/', 'unique:personal_ventas,dni'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'fecha_ingreso' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dni.regex' => 'El DNI debe contener exactamente 8 dígitos.',
            'dni.unique' => 'Ya existe personal de ventas registrado con ese DNI.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
