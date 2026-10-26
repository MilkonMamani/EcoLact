<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solo QUIÉN compra (`cliente`), el producto, la cantidad y la forma de
 * pago. No se acepta precio ni tipo de precio: RegistrarVentaProducto los
 * resuelve. Tampoco se valida aquí el stock ni el saldo del proveedor para
 * DESCUENTO_PLANILLA: esas reglas de negocio pertenecen al Use Case.
 */
class StoreVentaProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Compatibilidad con clientes que aún envían `tipo_cliente` (app móvil):
     * solo se usa para saber quién compra, nunca para fijar el precio.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('cliente') || ! $this->filled('tipo_cliente')) {
            return;
        }

        $this->merge(['cliente' => match (true) {
            $this->input('tipo_cliente') === 'PROVEEDOR' => 'PROVEEDOR',
            $this->filled('comprador_id') => 'COMPRADOR',
            default => 'PUBLICO',
        }]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cliente' => ['required', 'string', 'in:PUBLICO,COMPRADOR,PROVEEDOR'],
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'forma_pago' => ['required', 'string', 'in:CONTADO,DESCUENTO_PLANILLA'],
            'proveedor_id' => ['required_if:cliente,PROVEEDOR', 'nullable', 'integer', 'exists:proveedores,id'],
            'comprador_id' => ['required_if:cliente,COMPRADOR', 'nullable', 'integer', 'exists:compradores,id'],
        ];
    }

    /**
     * El id que no corresponde a quien compra se descarta.
     *
     * @return array{producto_id: int, cantidad: float, forma_pago: string, proveedor_id: ?int, comprador_id: ?int}
     */
    public function venta(): array
    {
        $datos = $this->validated();

        return [
            'producto_id' => (int) $datos['producto_id'],
            'cantidad' => (float) $datos['cantidad'],
            'forma_pago' => $datos['forma_pago'],
            'proveedor_id' => $datos['cliente'] === 'PROVEEDOR' ? (int) $datos['proveedor_id'] : null,
            'comprador_id' => $datos['cliente'] === 'COMPRADOR' ? (int) $datos['comprador_id'] : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.required_if' => 'Selecciona el proveedor que compra.',
            'comprador_id.required_if' => 'Selecciona el comprador registrado.',
        ];
    }
}
