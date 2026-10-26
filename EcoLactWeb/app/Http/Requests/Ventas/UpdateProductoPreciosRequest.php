<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoPreciosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Un precio vacío se retira; al menos uno debe quedar configurado (para
     * dejar el producto sin precios está "Eliminar precio").
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'precio_proveedor' => ['nullable', 'numeric', 'gt:0', 'required_without_all:precio_publico,precio_mayorista'],
            'precio_publico' => ['nullable', 'numeric', 'gt:0'],
            'precio_mayorista' => ['nullable', 'numeric', 'gt:0', 'required_with:cantidad_minima_mayorista'],
            'cantidad_minima_mayorista' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'precio_proveedor.required_without_all' => 'Configura al menos un precio.',
            'precio_mayorista.required_with' => 'Indica el precio mayorista para usar una cantidad mínima.',
        ];
    }
}
