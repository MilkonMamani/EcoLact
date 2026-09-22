<?php

namespace App\Http\Requests\Ruta;

use Illuminate\Foundation\Http\FormRequest;

class AgregarProveedorARutaRequest extends FormRequest
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
            'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
            'orden_visita' => ['nullable', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.exists' => 'El proveedor indicado no existe.',
            'orden_visita.min' => 'El orden de visita debe ser un entero positivo.',
        ];
    }
}
