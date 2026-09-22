<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoPrecioRequest extends FormRequest
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
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'tipo_cliente' => ['required', 'string', 'in:PROVEEDOR,MAYORISTA,PUBLICO'],
            'precio' => ['required', 'numeric', 'gt:0'],
            'fecha_inicio' => ['nullable', 'date'],
        ];
    }
}
