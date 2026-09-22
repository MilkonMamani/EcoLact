<?php

namespace App\Http\Requests\Pago;

use App\Domain\Shared\Enums\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato del registro de un pago. La obligación llega por route
 * model binding (no se elige beneficiario) y el monto es siempre el
 * calculado: si se envía `monto`, debe coincidir (la regla de negocio la
 * hace RegistrarPagoObligacion).
 */
class RegistrarPagoRequest extends FormRequest
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
        $metodo = MetodoPago::tryFrom((string) $this->input('metodo_pago'));

        return [
            'metodo_pago' => ['required', Rule::in(array_column(MetodoPago::cases(), 'value'))],
            'numero_operacion' => [Rule::requiredIf($metodo?->requiereNumeroOperacion() === true), 'nullable', 'string', 'max:100'],
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'monto' => ['nullable', 'numeric'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'comprobante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'metodo_pago.required' => 'Selecciona el método de pago.',
            'numero_operacion.required' => 'El número de operación o referencia es obligatorio para este método de pago.',
            'fecha_pago.required' => 'Indica la fecha de pago.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser posterior a hoy.',
            'comprobante.mimes' => 'El comprobante debe ser un PDF o una imagen (JPG, PNG o WEBP).',
            'comprobante.max' => 'El comprobante no puede pesar más de 5 MB.',
        ];
    }
}
