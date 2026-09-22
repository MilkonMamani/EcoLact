<?php

namespace App\Http\Requests\Produccion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida las líneas "producto + cantidad" de una nueva producción, tanto para
 * previsualizar los requerimientos como para iniciarla. La existencia de
 * receta y la suficiencia de stock las decide el Use Case, nunca este request.
 */
class RequerimientosProduccionRequest extends FormRequest
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
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'productos.required' => 'Agregue al menos un producto a elaborar.',
            'productos.min' => 'Agregue al menos un producto a elaborar.',
            'productos.*.producto_id.required' => 'Elija el producto de cada línea.',
            'productos.*.cantidad.gt' => 'La cantidad de cada producto debe ser mayor que cero.',
            'productos.*.cantidad.required' => 'Indique la cantidad de cada producto.',
        ];
    }
}
