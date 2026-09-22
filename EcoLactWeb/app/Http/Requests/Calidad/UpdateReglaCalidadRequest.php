<?php

namespace App\Http\Requests\Calidad;

use App\Domain\Shared\Enums\ResultadoCalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el formato de los datos HTTP para actualizar una regla de calidad
 * existente: valor_minimo, valor_maximo, resultado_incumplimiento,
 * prioridad, fecha_inicio, fecha_fin y estado.
 *
 * NO incluye parametro_id: ActualizarReglaCalidad no lo admite como
 * editable (una regla existe para un parámetro concreto; para evaluar otro
 * parámetro corresponde registrar una regla nueva).
 *
 * valor_minimo, valor_maximo y fecha_fin son columnas nullable en las que
 * "no enviar el campo" y "enviarlo explícitamente como null" significan
 * cosas distintas (ver ActualizarReglaCalidad). Por eso aquí solo se valida
 * el FORMATO de lo recibido con `sometimes` + `nullable`; la traducción de
 * "¿vino el campo en la petición?" a CampoActualizable es responsabilidad
 * del Controller que invoque el Use Case, no de este Request.
 *
 * No repite aquí la comparación fecha_fin vs fecha_inicio: en una
 * actualización parcial cualquiera de las dos puede no venir en esta
 * petición (se conservaría la vigente), así que la única comparación
 * correcta es la que hace ActualizarReglaCalidad contra los valores ya
 * resueltos.
 */
class UpdateReglaCalidadRequest extends FormRequest
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
            'valor_minimo' => ['sometimes', 'nullable', 'numeric'],
            'valor_maximo' => ['sometimes', 'nullable', 'numeric'],
            'resultado_incumplimiento' => ['sometimes', 'string', Rule::in(array_column(ResultadoCalidad::cases(), 'value'))],
            'prioridad' => ['sometimes', 'integer', 'min:0', 'max:255'],
            'fecha_inicio' => ['sometimes', 'date'],
            'fecha_fin' => ['sometimes', 'nullable', 'date'],
            'estado' => ['sometimes', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resultado_incumplimiento.in' => 'El resultado de incumplimiento no es un valor permitido.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $minimo = $this->input('valor_minimo');
            $maximo = $this->input('valor_maximo');

            // Solo se puede comparar aquí cuando AMBOS llegan en esta misma
            // petición; si falta alguno, la comparación real (contra el
            // valor vigente) la hace ActualizarReglaCalidad.
            if ($this->has('valor_minimo') && $this->has('valor_maximo')
                && $minimo !== null && $maximo !== null && (float) $minimo > (float) $maximo) {
                $validator->errors()->add('valor_minimo', 'El valor mínimo no puede ser mayor que el valor máximo.');
            }
        });
    }
}
