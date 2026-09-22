<?php

namespace App\Http\Requests\Calidad;

use App\Domain\Shared\Enums\ResultadoCalidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Valida el formato de los datos HTTP con los que el ADMINISTRADOR
 * configura una regla de calidad (parámetro, rango válido y resultado en
 * caso de incumplimiento).
 *
 * Sirve al Use Case RegistrarReglaCalidad (Etapa 07.1).
 */
class StoreReglaCalidadRequest extends FormRequest
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
            'parametro_id' => ['required', 'integer', 'exists:parametros_calidad,id'],
            'valor_minimo' => ['nullable', 'numeric'],
            'valor_maximo' => ['nullable', 'numeric'],
            'resultado_incumplimiento' => ['required', 'string', Rule::in(array_column(ResultadoCalidad::cases(), 'value'))],
            'prioridad' => ['nullable', 'integer', 'min:0', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'estado' => ['required', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parametro_id.exists' => 'El parámetro de calidad indicado no existe.',
            'resultado_incumplimiento.in' => 'El resultado de incumplimiento no es un valor permitido.',
            'fecha_fin.after_or_equal' => 'La fecha fin no puede ser anterior a la fecha inicio.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $minimo = $this->input('valor_minimo');
            $maximo = $this->input('valor_maximo');

            if ($minimo !== null && $maximo !== null && (float) $minimo > (float) $maximo) {
                $validator->errors()->add('valor_minimo', 'El valor mínimo no puede ser mayor que el valor máximo.');
            }
        });
    }
}
