<?php

namespace App\Http\Requests\Produccion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida las cantidades realmente obtenidas al finalizar un lote, indexadas
 * por el id de cada producto del lote. Puede ser 0 (una tanda que salió mal).
 */
class FinalizarProduccionRequest extends FormRequest
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
            'obtenido' => ['required', 'array', 'min:1'],
            'obtenido.*' => ['required', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obtenido.required' => 'Indique la cantidad realmente obtenida de cada producto.',
            'obtenido.*.required' => 'Indique la cantidad realmente obtenida de cada producto (puede ser 0).',
            'obtenido.*.numeric' => 'La cantidad obtenida debe ser un número.',
            'obtenido.*.min' => 'La cantidad obtenida no puede ser negativa.',
        ];
    }
}
