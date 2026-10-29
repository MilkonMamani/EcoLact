<?php

namespace App\Http\Requests\Compra;

use App\Domain\Shared\Enums\TipoDocumentoCompra;
use App\Http\Support\ComprobantesCompra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Documento adjuntado después a una compra (factura, constancia de pago...).
 */
class AdjuntarComprobanteCompraRequest extends FormRequest
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
            'archivo' => ['required', ...ComprobantesCompra::reglasArchivo()],
            'tipo' => ['required', Rule::enum(TipoDocumentoCompra::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Seleccione el documento.',
            'tipo.required' => 'Elija qué documento es.',
            ...ComprobantesCompra::mensajesArchivo('archivo'),
        ];
    }
}
