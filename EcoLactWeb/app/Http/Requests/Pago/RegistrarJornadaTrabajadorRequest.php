<?php

namespace App\Http\Requests\Pago;

use Illuminate\Foundation\Http\FormRequest;

/** Valida el registro de un día trabajado desde el perfil de un trabajador. */
class RegistrarJornadaTrabajadorRequest extends FormRequest
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
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha no es válida.',
            'fecha.before_or_equal' => 'No se puede registrar un día trabajado en el futuro.',
        ];
    }
}
