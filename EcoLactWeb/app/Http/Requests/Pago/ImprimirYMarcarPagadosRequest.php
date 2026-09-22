<?php

namespace App\Http\Requests\Pago;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de "Imprimir y marcar pagados". Los pagos llegan por id; el
 * monto que vio el administrador viaja solo para detectar cambios (el importe
 * real siempre es el calculado en el servidor).
 */
class ImprimirYMarcarPagadosRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:300'],
            'ids.*' => ['integer', 'min:1', 'distinct'],
            'montos' => ['nullable', 'array'],
            'montos.*' => ['nullable', 'numeric'],
            'ver' => ['nullable', 'integer', 'min:1'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Selecciona al menos un proveedor para imprimir y marcar como pagado.',
            'ids.min' => 'Selecciona al menos un proveedor para imprimir y marcar como pagado.',
            'ids.max' => 'Solo se pueden pagar hasta 300 proveedores a la vez.',
        ];
    }
}
