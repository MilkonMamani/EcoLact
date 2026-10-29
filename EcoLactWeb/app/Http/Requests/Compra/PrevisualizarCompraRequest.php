<?php

namespace App\Http\Requests\Compra;

use App\Domain\Shared\Enums\TratamientoCompra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Líneas de una compra o gasto para calcular la vista previa (no registra
 * nada). Que una línea de inventario tenga insumo y presentación válidos,
 * que no sea leche y los totales los decide CalcularCompra.
 */
class PrevisualizarCompraRequest extends FormRequest
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
            'lineas' => ['required', 'array', 'min:1', 'max:50'],
            'lineas.*.tratamiento' => ['required', Rule::enum(TratamientoCompra::class)],
            'lineas.*.insumo_id' => ['nullable', 'required_if:lineas.*.tratamiento,INVENTARIO', 'integer', 'exists:insumos,id'],
            'lineas.*.presentacion_id' => ['nullable', 'required_if:lineas.*.tratamiento,INVENTARIO', 'integer', 'exists:presentaciones_insumo,id'],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:150'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lineas.*.unidad' => ['nullable', 'string', 'max:20'],
            'lineas.*.precio_unitario' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'impuesto' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lineas.required' => 'Agregue al menos un artículo o concepto a la compra.',
            'lineas.*.tratamiento.required' => 'Indique el tratamiento de cada línea.',
            'lineas.*.insumo_id.required_if' => 'Seleccione el insumo de cada línea de inventario.',
            'lineas.*.presentacion_id.required_if' => 'Seleccione la presentación de cada línea de inventario.',
            'lineas.*.cantidad.required' => 'Indique la cantidad de cada línea.',
            'lineas.*.cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'lineas.*.precio_unitario.min' => 'El precio unitario no puede ser negativo.',
            'impuesto.min' => 'El impuesto no puede ser negativo.',
        ];
    }
}
