<?php

namespace App\Http\Requests\Pago;

use Illuminate\Foundation\Http\FormRequest;

/** Ids de los pagos de proveedor cuyo comprobante se quiere ver. */
class PrevisualizarComprobantesRequest extends FormRequest
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
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
