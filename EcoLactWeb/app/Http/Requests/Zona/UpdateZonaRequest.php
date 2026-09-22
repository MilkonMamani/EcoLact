<?php

namespace App\Http\Requests\Zona;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZonaRequest extends FormRequest
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
            'nombre' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('zonas', 'nombre')->ignore($this->route('zona')),
            ],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'estado' => ['sometimes', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe otra zona con ese nombre.',
        ];
    }
}
