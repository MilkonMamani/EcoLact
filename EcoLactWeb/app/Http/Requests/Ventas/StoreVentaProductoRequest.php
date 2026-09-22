<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NO valida aquí el stock disponible ni el saldo estimado del proveedor
 * para DESCUENTO_PLANILLA: esas reglas de negocio pertenecen al Use Case
 * RegistrarVentaProducto.
 */
class StoreVentaProductoRequest extends FormRequest
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
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'forma_pago' => ['required', 'string', 'in:CONTADO,DESCUENTO_PLANILLA'],
            'proveedor_id' => ['required_if:tipo_cliente,PROVEEDOR', 'nullable', 'integer', 'exists:proveedores,id'],
            'comprador_id' => ['nullable', 'integer', 'exists:compradores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.required_if' => 'Debe indicar el proveedor cuando el tipo de cliente es PROVEEDOR.',
        ];
    }
}
