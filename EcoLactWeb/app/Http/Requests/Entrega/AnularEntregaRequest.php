<?php

namespace App\Http\Requests\Entrega;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de la anulación de una entrega: solo exige un motivo. Qué
 * entregas se pueden anular (no valorizadas, leche aún disponible para
 * revertir) lo decide el Use Case AnularEntrega.
 */
class AnularEntregaRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Escriba el motivo de la anulación.',
        ];
    }
}
