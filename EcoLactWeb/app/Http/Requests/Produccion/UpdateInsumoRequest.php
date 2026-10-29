<?php

namespace App\Http\Requests\Produccion;

use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\TipoInsumo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la edición de la ficha de un insumo. Nunca incluye el stock. La
 * unidad base es opcional: ActualizarInsumo solo acepta cambiarla si el
 * insumo no tiene nada guardado en ella (stock, movimientos, compras...).
 */
class UpdateInsumoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:120', Rule::unique('insumos', 'nombre')->ignore($this->route('insumo'))],
            'tipo' => ['sometimes', 'string', Rule::in(array_column(TipoInsumo::cases(), 'value'))],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'unidad_base' => ['sometimes', 'string', Rule::in(UnidadMedida::codigos())],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un insumo registrado con ese nombre.',
            'unidad_base.in' => 'Elija una unidad válida: kilogramos, gramos, litros, mililitros o unidades.',
        ];
    }
}
