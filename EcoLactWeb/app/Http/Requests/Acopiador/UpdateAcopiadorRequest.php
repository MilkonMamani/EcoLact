<?php

namespace App\Http\Requests\Acopiador;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de los datos HTTP para actualizar un acopiador. Hay dos
 * formularios separados que llegan aquí:
 *
 * - "Editar información personal": nombres, apellidos y telefono.
 * - "Cambiar zona": zona_id y motivo_cambio_zona.
 *
 * dni y fecha_ingreso están PROHIBIDOS (se rechazan con 422 aunque se envíen
 * a mano): el DNI es la identidad operativa del acopiador y la fecha de
 * ingreso la fija el sistema al registrarlo. Tampoco admite username (gestión
 * de credenciales) ni estado (DesactivarAcopiador / ReactivarAcopiador).
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
            'fecha_ingreso' => ['prohibited'],
            'dni' => ['prohibited'],
            'nombres' => ['sometimes', 'required', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'required', 'string', 'max:150'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{6,20}$/'],
            'zona_id' => ['sometimes', 'required', 'integer', Rule::exists('zonas', 'id')->where('estado', 'ACTIVO')],
            'motivo_cambio_zona' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_ingreso.prohibited' => 'La fecha de ingreso la asigna el sistema y no se puede modificar.',
            'dni.prohibited' => 'El DNI es el identificador del acopiador y no se edita.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'telefono.regex' => 'El teléfono solo puede tener números (y opcionalmente + al inicio).',
            'zona_id.exists' => 'La zona seleccionada no existe o no está activa.',
        ];
    }
}
