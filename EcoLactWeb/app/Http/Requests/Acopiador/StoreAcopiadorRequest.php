<?php

namespace App\Http\Requests\Acopiador;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de los datos HTTP para registrar un acopiador.
 * No valida qué proveedores puede atender: eso pertenece a Application.
 *
 * Sirve al Use Case RegistrarAcopiador (Etapa 07.1).
 */
class StoreAcopiadorRequest extends FormRequest
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
            'dni' => ['required', 'string', 'regex:/^\d{8}$/', 'unique:acopiadores,dni'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
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
            'dni.unique' => 'Ya existe un acopiador registrado con ese DNI.',
            'zona_id.exists' => 'La zona seleccionada no existe.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
