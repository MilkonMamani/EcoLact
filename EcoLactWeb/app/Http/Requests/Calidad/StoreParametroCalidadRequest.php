<?php

namespace App\Http\Requests\Calidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de los datos HTTP para registrar un parámetro de
 * calidad (QUÉ se mide: código técnico, nombre, unidad). NO valida límites
 * de evaluación: esos pertenecen a StoreReglaCalidadRequest.
 *
 * Sirve al Use Case RegistrarParametroCalidad.
 */
class StoreParametroCalidadRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:40', 'unique:parametros_calidad,codigo'],
            'nombre' => ['required', 'string', 'max:100'],
            'unidad' => ['nullable', 'string', 'max:30'],
            'estado' => ['required', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ya existe un parámetro de calidad con ese código.',
        ];
    }
}
