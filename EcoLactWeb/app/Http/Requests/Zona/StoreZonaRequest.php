<?php

namespace App\Http\Requests\Zona;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZonaRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:100', 'unique:zonas,nombre'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe una zona con ese nombre.',
        ];
    }
}
