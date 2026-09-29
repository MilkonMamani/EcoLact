<?php

namespace App\Http\Requests\AnalistaCalidad;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnalistaCalidadRequest extends FormRequest
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
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:150'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
