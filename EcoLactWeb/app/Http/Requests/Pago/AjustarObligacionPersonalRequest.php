<?php

namespace App\Http\Requests\Pago;

use Illuminate\Foundation\Http\FormRequest;

/** Valida el ajuste de bonificación/descuento del panel de "Pagar a trabajadores". */
class AjustarObligacionPersonalRequest extends FormRequest
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
            'bonificacion' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'descuento' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bonificacion.numeric' => 'La bonificación debe ser un número.',
            'bonificacion.min' => 'La bonificación no puede ser negativa.',
            'descuento.numeric' => 'El descuento debe ser un número.',
            'descuento.min' => 'El descuento no puede ser negativo.',
        ];
    }
}
