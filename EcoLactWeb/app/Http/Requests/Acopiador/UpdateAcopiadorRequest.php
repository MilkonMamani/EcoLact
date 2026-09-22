<?php

namespace App\Http\Requests\Acopiador;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de los datos HTTP para actualizar un acopiador:
 * nombres, apellidos, telefono, zona y motivo_cambio_zona.
 *
 * NO incluye dni ni username: ActualizarAcopiador no los admite (el DNI es
 * la identidad operativa del acopiador y el username pertenece a la
 * gestión de credenciales, fuera de este flujo).
 *
 * NO incluye estado: el ciclo ACTIVO/INACTIVO del acopiador ya no pasa por
 * este formulario normal de edición — tiene sus propios Use Cases/Requests
 * dedicados (DesactivarAcopiador, ReactivarAcopiador + ReactivarAcopiadorRequest),
 * que además cierran/abren el historial de zona correspondiente.
 */
class UpdateAcopiadorRequest extends FormRequest
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
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:150'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20'],
            'zona_id' => ['sometimes', 'integer', 'exists:zonas,id'],
            'motivo_cambio_zona' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zona_id.exists' => 'La zona seleccionada no existe.',
        ];
    }
}
