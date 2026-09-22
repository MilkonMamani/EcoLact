<?php

namespace App\Http\Requests\Produccion;

use App\Domain\Produccion\UnidadMedida;
use App\Domain\Shared\Enums\TipoInsumo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el alta de un insumo. La unidad base es la unidad en la que se
 * controlará todo su stock y sus movimientos. El insumo del sistema (Leche)
 * no se crea por aquí.
 */
class StoreInsumoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El cliente móvil anterior enviaba `unidad_medida`; se acepta como
     * `unidad_base`. Sin tipo, el insumo es un INSUMO común.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'unidad_base' => $this->input('unidad_base', $this->input('unidad_medida')),
            'tipo' => $this->input('tipo') ?: TipoInsumo::INSUMO->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120', 'unique:insumos,nombre'],
            'tipo' => ['required', 'string', Rule::in(array_column(TipoInsumo::cases(), 'value'))],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'unidad_base' => ['required', 'string', Rule::in(UnidadMedida::codigos())],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'stock_inicial' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un insumo registrado con ese nombre.',
            'unidad_base.in' => 'Elija una unidad válida: litros, mililitros, kilogramos, gramos o unidades.',
        ];
    }
}
