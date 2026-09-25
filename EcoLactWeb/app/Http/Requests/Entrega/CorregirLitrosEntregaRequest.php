<?php

namespace App\Http\Requests\Entrega;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la corrección de litros desde el modal "Ver entrega": el único campo
 * aceptado es `litros` (cualquier otro campo enviado se ignora). La precisión
 * es la de la columna entregas.litros, DECIMAL(10,2). Las reglas de negocio
 * (estado de la entrega, pagos realizados) pertenecen al Use Case
 * CorregirLitrosEntrega.
 */
class CorregirLitrosEntregaRequest extends FormRequest
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
            'litros' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'litros.required' => 'Ingresa la cantidad de litros.',
            'litros.numeric' => 'Los litros deben ser un número.',
            'litros.gt' => 'Los litros deben ser mayores a 0.',
            'litros.decimal' => 'Los litros admiten como máximo 2 decimales.',
            'litros.max' => 'La cantidad de litros es demasiado grande.',
        ];
    }
}
