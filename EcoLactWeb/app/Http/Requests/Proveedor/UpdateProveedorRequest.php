<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de los datos HTTP para actualizar un proveedor:
 * dni, nombres, apellidos y telefono.
 *
 * El dni no se marca como duplicado contra sí mismo (se ignora el propio
 * registro mediante el parámetro de ruta {proveedor}); la comprobación real
 * de unicidad contra otros proveedores la repite Application
 * (ActualizarProveedor) porque es una regla de negocio, no solo HTTP.
 *
 * NO incluye zona_id ni motivo_cambio_zona: la zona ya no se edita desde
 * este formulario, se deriva de la asignación a una ruta (módulo Zonas).
 * NO incluye username: la cuenta de acceso del proveedor se administra
 * mediante una funcionalidad específica de usuarios/credenciales, fuera de
 * este flujo. ActualizarProveedor tampoco lo acepta.
 */
class UpdateProveedorRequest extends FormRequest
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
        $proveedorId = $this->route('proveedor');

        return [
            'dni' => [
                'sometimes', 'string', 'regex:/^\d{8}$/',
                Rule::unique('proveedores', 'dni')->ignore($proveedorId),
            ],
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:150'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dni.regex' => 'El DNI debe contener exactamente 8 dígitos.',
            'dni.unique' => 'Ya existe otro proveedor registrado con ese DNI.',
        ];
    }
}
