<?php

namespace App\Http\Requests\Ruta;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRutaRequest extends FormRequest
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
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'estado' => ['sometimes', 'string', 'in:ACTIVO,INACTIVO'],
        ];
    }
}
