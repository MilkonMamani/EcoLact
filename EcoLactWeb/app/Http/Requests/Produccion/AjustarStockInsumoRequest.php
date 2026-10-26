<?php

namespace App\Http\Requests\Produccion;

use App\Application\Produccion\AjustarStockInsumo;
use App\Domain\Shared\Enums\MotivoAjusteInsumo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Ajustar stock" del administrador: motivo (merma, pérdida, corrección o
 * inventario físico), cantidad en la unidad base y, solo para corrección, si
 * suma o resta. Lo que significa cada motivo lo decide AjustarStockInsumo.
 */
class AjustarStockInsumoRequest extends FormRequest
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
            'motivo' => ['required', 'string', Rule::enum(MotivoAjusteInsumo::class)],
            'direccion' => ['nullable', 'required_if:motivo,'.MotivoAjusteInsumo::CORRECCION->value, Rule::in([AjustarStockInsumo::SUMA, AjustarStockInsumo::RESTA])],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Elija el motivo del ajuste.',
            'motivo.enum' => 'Elija un motivo válido: merma, pérdida, corrección o inventario físico.',
            'direccion.required_if' => 'Indique si la corrección suma o resta stock.',
            'cantidad.required' => 'Indique la cantidad.',
        ];
    }
}
