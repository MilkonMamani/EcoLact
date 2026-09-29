<?php

namespace App\Http\Requests\Acopiador;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de los datos HTTP para reactivar un acopiador: la zona
 * a la que será reasignado (existente y ACTIVA). Sirve al Use Case
 * ReactivarAcopiador, que repite la regla por ser de negocio.
 */
class ReactivarAcopiadorRequest extends FormRequest
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
            'zona_id' => ['required', 'integer', Rule::exists('zonas', 'id')->where('estado', 'ACTIVO')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zona_id.required' => 'Selecciona la zona a la que se reincorpora.',
            'zona_id.exists' => 'La zona seleccionada no existe o no está activa.',
        ];
    }
}
