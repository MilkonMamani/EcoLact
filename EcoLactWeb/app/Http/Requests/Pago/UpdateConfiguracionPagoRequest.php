<?php

namespace App\Http\Requests\Pago;

use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el cambio de una configuración de pago (nueva vigencia). El tipo, el
 * rol y el cálculo no se cambian: para eso se crea otra configuración.
 */
class UpdateConfiguracionPagoRequest extends FormRequest
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
        $configuracion = $this->route('configuracion');
        $esPersonal = $configuracion instanceof ConfiguracionPagoModel && $configuracion->tipo_beneficiario === 'PERSONAL';

        return [
            'monto_base' => [Rule::requiredIf($esPersonal), 'nullable', 'numeric', 'gt:0', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'precio_base_litro' => [Rule::requiredIf(! $esPersonal), 'nullable', 'numeric', 'gt:0', 'max:999999.9999', 'regex:/^\d+(\.\d{1,4})?$/'],
            'aplica_calidad' => ['nullable', 'boolean'],
            'ajustes' => ['nullable', 'array'],
            'ajustes.*.tipo_ajuste' => ['nullable', Rule::in(['MONTO', 'PORCENTAJE'])],
            'ajustes.*.valor_ajuste' => ['nullable', 'numeric', 'between:-999999,999999'],
            'fecha_inicio' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto_base.required' => 'Ingresa el sueldo mensual.',
            'monto_base.gt' => 'El sueldo mensual debe ser mayor que cero.',
            'monto_base.regex' => 'El sueldo mensual debe tener como máximo dos decimales.',
            'precio_base_litro.required' => 'Ingresa el precio base por litro.',
            'precio_base_litro.gt' => 'El precio base por litro debe ser mayor que cero.',
            'precio_base_litro.regex' => 'El precio base por litro admite como máximo cuatro decimales.',
            'fecha_inicio.required' => 'Indica la fecha de inicio de la nueva vigencia.',
        ];
    }
}
