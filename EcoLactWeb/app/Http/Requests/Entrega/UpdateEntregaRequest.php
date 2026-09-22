<?php

namespace App\Http\Requests\Entrega;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NO valida que la entrega siga PENDIENTE_ANALISIS ni que el proveedor y
 * acopiador pertenezcan a la misma zona: esas reglas de negocio pertenecen
 * al Use Case ActualizarEntrega.
 */
class UpdateEntregaRequest extends FormRequest
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
            'motivo_modificacion' => ['required', 'string', 'max:255'],
            'proveedor_id' => ['sometimes', 'integer', 'exists:proveedores,id'],
            'zona_id' => ['sometimes', 'integer', 'exists:zonas,id'],
            'acopiador_id' => ['sometimes', 'nullable', 'integer', 'exists:acopiadores,id'],
            'fecha_hora' => ['sometimes', 'date'],
            'litros' => ['sometimes', 'numeric', 'gt:0'],
            'metodo_identificacion' => ['sometimes', 'string', 'in:QR,DNI'],
            'observacion' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
