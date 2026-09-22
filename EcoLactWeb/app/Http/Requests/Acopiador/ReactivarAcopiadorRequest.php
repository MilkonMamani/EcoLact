<?php

namespace App\Http\Requests\Acopiador;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de los datos HTTP para reactivar un acopiador: la zona
 * a la que será reasignado. Sirve al Use Case ReactivarAcopiador.
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
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
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
