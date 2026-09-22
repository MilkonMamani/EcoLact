<?php

namespace App\Http\Requests\Recorrido;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarAcopioRecorridoRequest extends FormRequest
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
            'litros' => ['required', 'numeric', 'gt:0'],
            'fecha_hora_ordeño' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'token_idempotencia' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'litros.gt' => 'Los litros deben ser un número mayor a cero.',
        ];
    }
}
