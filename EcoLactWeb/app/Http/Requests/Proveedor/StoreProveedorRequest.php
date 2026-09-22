<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de los datos HTTP para registrar un proveedor.
 * Las reglas de negocio (coordinación con Usuario, generación del qr_token,
 * etc.) pertenecen al Use Case RegistrarProveedor.
 *
 * NO incluye zona_id: el proveedor se registra independientemente de su
 * zona; la asignación a una ruta (de la que se deriva su zona) se hace
 * después desde el módulo Zonas.
 */
class StoreProveedorRequest extends FormRequest
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
            'dni' => ['required', 'string', 'regex:/^\d{8}$/', 'unique:proveedores,dni'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'fecha_registro' => ['nullable', 'date'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dni.regex' => 'El DNI debe contener exactamente 8 dígitos.',
            'dni.unique' => 'Ya existe un proveedor registrado con ese DNI.',
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
