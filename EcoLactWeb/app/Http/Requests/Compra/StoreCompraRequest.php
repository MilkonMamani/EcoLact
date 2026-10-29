<?php

namespace App\Http\Requests\Compra;

use App\Domain\Shared\Enums\CondicionPagoCompra;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Shared\Enums\TipoComprobanteCompra;
use App\Domain\Shared\Enums\TratamientoCompra;
use App\Http\Support\ComprobantesCompra;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Confirmación de una compra o gasto (etapa «Revisar y confirmar»). Además de
 * las líneas pide los datos de compra y la condición de pago; la recepción solo
 * si alguna línea entra al inventario. Cada línea necesita precio: sin total no
 * se sabe cuánto pagar ni cuánto se debe. Sin tipo de compra, RegistrarCompra lo
 * deduce de las líneas (insumos de producción u otros gastos).
 *
 * `documento_escaneado` es la ruta que devolvió "Escanear boleta": solo se
 * acepta una ruta de esa carpeta privada que exista. `documentos` son
 * archivos adjuntos a mano (imagen o PDF). El actor es siempre el usuario
 * autenticado.
 */
class StoreCompraRequest extends PrevisualizarCompraRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'lineas.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'categoria_compra_id' => ['nullable', 'integer', Rule::exists('categorias_compra', 'id')->where('estado', 'ACTIVA')],
            'proveedor_comercial' => ['nullable', 'string', 'max:150'],
            'ruc' => ['nullable', 'digits:11'],
            'tipo_comprobante' => ['required', Rule::enum(TipoComprobanteCompra::class)],
            'numero_comprobante' => ['nullable', 'string', 'max:30'],
            'moneda' => ['nullable', Rule::in(['PEN'])],
            'concepto' => ['nullable', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'recepcion' => [Rule::requiredIf(fn (): bool => collect((array) $this->input('lineas', []))->contains(fn ($linea): bool => is_array($linea) && ($linea['tratamiento'] ?? null) === TratamientoCompra::INVENTARIO->value)), 'nullable', Rule::in(['RECIBIDA', 'PENDIENTE'])],
            'condicion_pago' => ['required', Rule::enum(CondicionPagoCompra::class)],
            'monto_pago' => ['nullable', 'required_if:condicion_pago,PARCIAL', 'numeric', 'gt:0'],
            'metodo_pago' => ['nullable', 'required_unless:condicion_pago,CREDITO', Rule::enum(MetodoPago::class)],
            'numero_operacion' => ['nullable', 'string', 'max:60'],
            'documento_escaneado' => ['nullable', 'string', 'max:255'],
            'documentos' => ['nullable', 'array', 'max:5'],
            'documentos.*' => ComprobantesCompra::reglasArchivo(),
            'clave_envio' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ruta = $this->input('documento_escaneado');

                if ($ruta !== null && $ruta !== '' && ! ComprobantesCompra::esEscaneoValido($ruta)) {
                    $validator->errors()->add('documento_escaneado', 'La foto de la boleta no es válida. Vuelve a escanearla.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'lineas.*.precio_unitario.required' => 'Indique el precio unitario de cada línea.',
            'fecha.required' => 'Indique la fecha de la compra.',
            'fecha.before_or_equal' => 'La fecha de la compra no puede ser futura.',
            'categoria_compra_id.exists' => 'Elija un tipo de compra válido.',
            'tipo_comprobante.required' => 'Elija el tipo de comprobante.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'moneda.in' => 'Por ahora las compras se registran en soles.',
            'recepcion.required' => 'Indique si los artículos de inventario ya llegaron.',
            'condicion_pago.required' => 'Elija la condición de pago.',
            'monto_pago.required_if' => 'Indique cuánto se paga ahora.',
            'monto_pago.gt' => 'El pago inicial debe ser mayor que cero.',
            'metodo_pago.required_unless' => 'Elija el método de pago.',
            'documentos.max' => 'Adjunte como máximo 5 documentos.',
            ...ComprobantesCompra::mensajesArchivo('documentos.*'),
        ];
    }
}
