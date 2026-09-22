<?php

namespace App\Http\Requests\Calidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de los datos HTTP para actualizar un parámetro de
 * calidad: nombre, unidad y estado.
 *
 * NO incluye codigo: es el identificador técnico estable del parámetro
 * (GRASA no puede convertirse después en PROTEINA) y ActualizarParametroCalidad
 * no lo admite como editable.
 */
class UpdateParametroCalidadRequest extends FormRequest
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
            'nombre' => ['sometimes', 'string', 'max:100'],
            'unidad' => ['sometimes', 'nullable', 'string', 'max:30'],
            'estado' => ['sometimes', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }
}
