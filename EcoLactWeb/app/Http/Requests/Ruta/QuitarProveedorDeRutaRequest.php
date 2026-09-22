<?php

namespace App\Http\Requests\Ruta;

use Illuminate\Foundation\Http\FormRequest;

class QuitarProveedorDeRutaRequest extends FormRequest
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
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
