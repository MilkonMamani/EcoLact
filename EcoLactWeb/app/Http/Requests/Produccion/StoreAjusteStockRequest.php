<?php

namespace App\Http\Requests\Produccion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El elemento_id se valida contra productos o insumos según el tipo (la
 * tabla depende de un campo del propio request), así que la existencia real
 * la comprueban RegistrarAjusteProducto/RegistrarAjusteInsumo, no esta regla.
 */
class StoreAjusteStockRequest extends FormRequest
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
            'tipo' => ['required', 'string', 'in:PRODUCTO,INSUMO'],
            'elemento_id' => ['required', 'integer'],
            'nuevo_stock' => ['required', 'numeric', 'gte:0'],
            'motivo' => ['required', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
        ];
    }
}
