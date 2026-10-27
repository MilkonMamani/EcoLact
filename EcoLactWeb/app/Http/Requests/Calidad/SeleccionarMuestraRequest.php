<?php

namespace App\Http\Requests\Calidad;

use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Domain\Shared\Enums\TipoControlMuestreo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formato de "Confirmar muestra". Las reglas de negocio (entrega elegible,
 * sin duplicados) viven en SeleccionarMuestraCalidad. Una selección
 * ALEATORIA debe traer el `propuesta_token` que entregó el servidor al
 * sortearla: el teléfono no puede declarar "aleatoria" una entrega elegida a mano.
 */
class SeleccionarMuestraRequest extends FormRequest
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
            'entrega_id' => ['required', 'integer', 'exists:entregas,id'],
            'metodo_seleccion' => ['required', Rule::in([MetodoSeleccionMuestreo::ALEATORIA->value, MetodoSeleccionMuestreo::MANUAL->value])],
            'tipo_control' => ['nullable', Rule::enum(TipoControlMuestreo::class)],
            'propuesta_token' => ['required_if:metodo_seleccion,ALEATORIA', 'nullable', 'string'],
            // Hora real de la selección en el teléfono (operación offline sincronizada después).
            'fecha_seleccion' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entrega_id.exists' => 'La entrega indicada no existe.',
            'metodo_seleccion.in' => 'El método de selección debe ser ALEATORIA o MANUAL.',
            'propuesta_token.required_if' => 'La muestra aleatoria debe venir del sorteo del servidor.',
        ];
    }
}
