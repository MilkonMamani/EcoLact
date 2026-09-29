<?php

namespace App\Http\Requests\EncargadoPlanta;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NO incluye dni, username ni estado: mismo criterio que
 * UpdateAcopiadorRequest (identidad operativa y credenciales fuera de este
 * flujo; el ciclo ACTIVO/INACTIVO tiene sus propios Use Cases dedicados).
 */
class UpdateEncargadoPlantaRequest extends FormRequest
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
            'fecha_ingreso' => ['prohibited'],
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:150'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
