<?php

namespace App\Http\Requests\Pago;

use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Shared\Enums\TipoCalculoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de la Nueva configuración de pago. Un proveedor se paga por
 * litros (con precio base y ajustes por calidad opcionales); el personal cobra
 * un sueldo fijo mensual para un rol de la tabla roles marcado como
 * remunerable. Que el acopiador no se pague por litros lo garantiza esta
 * validación (tipo de cálculo y periodicidad fijos por tipo).
 */
class StoreConfiguracionPagoRequest extends FormRequest
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
        $esPersonal = $this->input('tipo_beneficiario') === TipoBeneficiarioPago::PERSONAL->value;
        $esProveedor = $this->input('tipo_beneficiario') === TipoBeneficiarioPago::PROVEEDOR->value;

        return [
            'tipo_beneficiario' => ['required', Rule::in(array_column(TipoBeneficiarioPago::cases(), 'value'))],
            'rol_id' => [Rule::requiredIf($esPersonal), 'nullable', 'integer', Rule::exists('roles', 'id')->where('es_remunerable', true)],
            'tipo_calculo' => ['nullable', Rule::in($esPersonal ? [TipoCalculoPago::SUELDO_FIJO->value] : [TipoCalculoPago::POR_LITROS->value])],
            'periodicidad' => ['nullable', Rule::in($esPersonal ? [PeriodicidadPago::MENSUAL->value] : [PeriodicidadPago::SEMANAL->value])],
            'monto_base' => [Rule::requiredIf($esPersonal), 'nullable', 'numeric', 'gt:0', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'precio_base_litro' => [Rule::requiredIf($esProveedor), 'nullable', 'numeric', 'gt:0', 'max:999999.9999', 'regex:/^\d+(\.\d{1,4})?$/'],
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
            'tipo_beneficiario.required' => 'Selecciona si la configuración es para Proveedor o Personal.',
            'rol_id.required' => 'Selecciona el rol del sistema.',
            'rol_id.exists' => 'El rol seleccionado no existe o no es remunerable.',
            'tipo_calculo.in' => 'El tipo de cálculo no es válido para este beneficiario: el personal cobra sueldo fijo mensual y el proveedor por litros.',
            'periodicidad.in' => 'La periodicidad no es válida: el personal se paga mensual y el proveedor semanal.',
            'monto_base.required' => 'Ingresa el sueldo mensual.',
            'monto_base.gt' => 'El sueldo mensual debe ser mayor que cero.',
            'monto_base.regex' => 'El sueldo mensual debe tener como máximo dos decimales.',
            'precio_base_litro.required' => 'Ingresa el precio base por litro.',
            'precio_base_litro.gt' => 'El precio base por litro debe ser mayor que cero.',
            'precio_base_litro.regex' => 'El precio base por litro admite como máximo cuatro decimales.',
            'ajustes.*.valor_ajuste.numeric' => 'El valor del ajuste por calidad debe ser un número.',
            'fecha_inicio.required' => 'Indica la fecha de inicio de la configuración.',
        ];
    }
}
