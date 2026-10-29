<?php

namespace App\Http\Requests\Compra;

use App\Domain\Shared\Enums\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pago posterior (parcial o del saldo) de una compra. Que no supere el saldo
 * lo decide RegistrarPagoCompra con la compra bloqueada.
 */
class RegistrarPagoCompraRequest extends FormRequest
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
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'numero_operacion' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'clave_envio' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.required' => 'Indique el monto del pago.',
            'monto.gt' => 'El monto del pago debe ser mayor que cero.',
            'metodo_pago.required' => 'Elija el método de pago.',
        ];
    }
}
