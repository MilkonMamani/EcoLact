<?php

namespace App\Http\Requests\Recorrido;

use Illuminate\Foundation\Http\FormRequest;

class AbrirRecorridoRequest extends FormRequest
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
            'acopiador_id' => ['required', 'integer', 'exists:acopiadores,id'],
            'fecha_operativa' => ['nullable', 'date'],
        ];
    }
}
