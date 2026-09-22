<?php

namespace App\Http\Requests\Produccion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el registro manual de un movimiento de insumo (entrada/salida)
 * desde el panel de ENCARGADO_PLANTA. NO se usa para el consumo automático
 * de producción (eso lo genera IniciarProduccion) ni para compras, que ya
 * tienen su propio flujo (RegistrarCompraInsumo); aquí se traduce siempre a
 * un ajuste de stock (RegistrarAjusteInsumo), calculando el nuevo stock a
 * partir de tipo + cantidad.
 */
class StoreMovimientoInsumoRequest extends FormRequest
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
            'tipo' => ['required', 'string', Rule::in(['ENTRADA', 'SALIDA'])],
            'motivo' => ['required', 'string', 'max:30'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo de movimiento debe ser Entrada o Salida.',
            'cantidad.gt' => 'La cantidad debe ser mayor que cero.',
        ];
    }
}
