<?php

namespace App\Http\Requests\Proveedor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formato de los datos HTTP para reactivar un proveedor: la zona
 * a la que se reincorpora (existente y ACTIVA) y la ruta de acopio de esa
 * misma zona (existente y ACTIVA), porque la zona del proveedor se deriva
 * de su ruta. Sirve al Use Case ReactivarProveedor, que repite las reglas
 * de negocio.
 */
class ReactivarProveedorRequest extends FormRequest
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
            'zona_id' => ['required', 'integer', Rule::exists('zonas', 'id')->where('estado', 'ACTIVO')],
            'ruta_id' => [
                'required', 'integer',
                Rule::exists('rutas', 'id')->where('estado', 'ACTIVO')->where('zona_id', (int) $this->input('zona_id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zona_id.required' => 'Selecciona la zona a la que se reincorpora.',
            'zona_id.exists' => 'La zona seleccionada no existe o no está activa.',
            'ruta_id.required' => 'Selecciona la ruta de acopio de esa zona.',
            'ruta_id.exists' => 'La ruta seleccionada no pertenece a la zona o no está activa.',
        ];
    }
}
