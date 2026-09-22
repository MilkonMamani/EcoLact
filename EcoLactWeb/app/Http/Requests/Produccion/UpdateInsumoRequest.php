<?php

namespace App\Http\Requests\Produccion;

use App\Domain\Shared\Enums\TipoInsumo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la edición de los datos no críticos de un insumo. No incluye la
 * unidad base ni el stock: cambiarlos reinterpretaría el historial.
 */
class UpdateInsumoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:120', Rule::unique('insumos', 'nombre')->ignore($this->route('insumo'))],
            'tipo' => ['sometimes', 'string', Rule::in(array_column(TipoInsumo::cases(), 'value'))],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un insumo registrado con ese nombre.',
        ];
    }
}
