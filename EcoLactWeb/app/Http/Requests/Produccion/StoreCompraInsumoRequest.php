<?php

namespace App\Http\Requests\Produccion;

use App\Domain\Produccion\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompraInsumoRequest extends FormRequest
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
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'unidad' => ['nullable', 'string', Rule::in(UnidadMedida::codigos())],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidad.gt' => 'La cantidad debe ser mayor que cero.',
        ];
    }
}
