<?php

namespace App\Http\Requests\Comunicacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NO valida que el comunicado siga en BORRADOR: esa regla de negocio
 * pertenece al Use Case ActualizarComunicado.
 */
class UpdateComunicadoRequest extends FormRequest
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
            'titulo' => ['sometimes', 'string', 'max:180'],
            'contenido' => ['sometimes', 'string'],
            'tipo' => ['sometimes', 'string', 'max:30'],
            'fecha_evento' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
