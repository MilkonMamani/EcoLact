<?php

namespace App\Http\Requests\Zona;

use Illuminate\Foundation\Http\FormRequest;

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
