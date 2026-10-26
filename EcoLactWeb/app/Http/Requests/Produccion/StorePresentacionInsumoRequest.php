<?php

namespace App\Http\Requests\Produccion;

use App\Domain\Produccion\PresentacionInsumo;
use App\Domain\Produccion\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Presentación de compra de un insumo ("Saco" que contiene 25 kg). Que la
 * unidad sea compatible con la unidad base del insumo lo decide
 * PresentacionInsumo, no esta regla.
 */
class StorePresentacionInsumoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:60'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'unidad' => ['required', 'string', Rule::in(UnidadMedida::codigos())],
            'estado' => ['nullable', Rule::in([PresentacionInsumo::ACTIVA, PresentacionInsumo::INACTIVA])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre de la presentación (por ejemplo, Saco).',
            'cantidad.required' => 'Indique cuánto contiene la presentación.',
            'cantidad.gt' => 'El contenido de la presentación debe ser mayor que cero.',
            'unidad.in' => 'Elija una unidad válida.',
        ];
    }
}
