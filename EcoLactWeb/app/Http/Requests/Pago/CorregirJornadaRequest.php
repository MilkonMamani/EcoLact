<?php

namespace App\Http\Requests\Pago;

use Illuminate\Foundation\Http\FormRequest;

/** Valida la corrección administrativa de una jornada (FALTA<->PRESENTE): el motivo es obligatorio. */
class CorregirJornadaRequest extends FormRequest
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
            'estado' => ['required', 'in:PRESENTE,FALTA'],
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El nuevo estado es obligatorio.',
            'estado.in' => 'El estado debe ser PRESENTE o FALTA.',
            'motivo.required' => 'El motivo de la corrección es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos 3 caracteres.',
        ];
    }
}
