<?php

namespace App\Http\Requests\Pago;

use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la primera configuración de pago de cada pestaña:
 *
 * - Trabajadores (PERSONAL): el trabajador (user_id) y su sueldo mensual. El rol,
 *   el sueldo fijo mensual y la base de 30 días son fijos: no se envían.
 * - Proveedores (PROVEEDOR): precio base por litro y descuento por litro para la
 *   semana OBSERVADA. Semanal y pago el miércoles, también fijos.
 *
 * Que el trabajador sea real y activo, y su rol, lo valida RegistrarConfiguracionPago.
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
            'user_id' => [Rule::requiredIf($esPersonal), 'nullable', 'integer', Rule::exists('users', 'id')],
            'monto_base' => [Rule::requiredIf($esPersonal), 'nullable', 'numeric', 'gt:0', 'max:9999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'precio_base_litro' => [Rule::requiredIf($esProveedor), 'nullable', 'numeric', 'gt:0', 'max:999999.9999', 'regex:/^\d+(\.\d{1,4})?$/'],
            'descuento_observada' => ['nullable', 'numeric', 'min:0', 'lt:precio_base_litro', 'regex:/^\d+(\.\d{1,4})?$/'],
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
            'tipo_beneficiario.required' => 'Indica si la configuración es de un trabajador o de proveedores.',
            'user_id.required' => 'Selecciona el trabajador.',
            'user_id.exists' => 'El trabajador seleccionado no existe.',
            'monto_base.required' => 'Ingresa el sueldo mensual.',
            'monto_base.gt' => 'El sueldo mensual debe ser mayor que cero.',
            'monto_base.regex' => 'El sueldo mensual debe tener como máximo dos decimales.',
            'precio_base_litro.required' => 'Ingresa el precio base por litro.',
            'precio_base_litro.gt' => 'El precio base por litro debe ser mayor que cero.',
            'precio_base_litro.regex' => 'El precio base por litro admite como máximo cuatro decimales.',
            'descuento_observada.min' => 'El descuento por litro no puede ser negativo.',
            'descuento_observada.lt' => 'El descuento por litro debe ser menor que el precio base.',
            'descuento_observada.regex' => 'El descuento por litro admite como máximo cuatro decimales.',
            'fecha_inicio.required' => 'Indica la fecha de inicio de la vigencia.',
        ];
    }
}
