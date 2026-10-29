<?php

namespace App\Http\Requests\Produccion;

use App\Http\Support\ComprobantesCompra;
use App\Http\Support\FechaEventoMovil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

/**
 * Líneas "insumo + presentación + cantidad" de una compra (para previsualizar
 * y para confirmar) y los datos opcionales del comprobante. Que la
 * presentación pertenezca al insumo, que no sea leche y los totales los
 * decide CalcularCompraInsumos, no esta regla.
 *
 * `imagen` es una foto opcional adjuntada en el registro manual; la guarda el
 * controlador. `imagen_boleta` es la foto que ya guardó "Procesar boleta": solo se acepta
 * una ruta de esa carpeta que exista, así el request nunca apunta a un archivo
 * arbitrario.
 */
class CompraInsumosRequest extends FormRequest
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
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.insumo_id' => ['required', 'integer', 'exists:insumos,id'],
            'lineas.*.presentacion_id' => ['required', 'integer', 'exists:presentaciones_insumo,id'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'proveedor_comercial' => ['nullable', 'string', 'max:150'],
            'ruc' => ['nullable', 'digits:11'],
            'numero_comprobante' => ['nullable', 'string', 'max:30'],
            'fecha' => ['nullable', 'date', 'before_or_equal:today'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'imagen_boleta' => ['nullable', 'string', 'max:255'],
            // Foto adjuntada a mano en "Registrar compra" (sin pasar por el OCR).
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            // App móvil: hora real de la confirmación; si la boleta no trae fecha, la compra es de ese día.
            'fecha_hora_evento' => FechaEventoMovil::reglas(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ruta = $this->input('imagen_boleta');

                if ($ruta === null || $ruta === '') {
                    return;
                }

                $patron = '#^'.preg_quote(ComprobantesCompra::CARPETA_BOLETAS_APP, '#').'/boleta-[a-z0-9]{24}\.(jpg|jpeg|png|webp)$#';

                if (! is_string($ruta) || ! preg_match($patron, $ruta) || ! Storage::disk('public')->exists($ruta)) {
                    $validator->errors()->add('imagen_boleta', 'La foto de la boleta no es válida. Vuelve a escanearla.');
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
            'lineas.required' => 'Agregue al menos un insumo a la compra.',
            'lineas.*.insumo_id.required' => 'Seleccione el insumo de cada línea.',
            'lineas.*.presentacion_id.required' => 'Seleccione la presentación de cada línea.',
            'lineas.*.cantidad.required' => 'Indique la cantidad comprada de cada línea.',
            'lineas.*.cantidad.gt' => 'La cantidad comprada debe ser mayor que cero.',
            'lineas.*.precio_unitario.min' => 'El precio unitario no puede ser negativo.',
            'imagen.image' => 'La boleta debe ser una imagen (JPG, PNG o WEBP).',
            'imagen.max' => 'La foto de la boleta no puede pesar más de 8 MB.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'fecha.before_or_equal' => 'La fecha de la compra no puede ser futura.',
            ...FechaEventoMovil::mensajes('fecha_hora_evento'),
        ];
    }
}
