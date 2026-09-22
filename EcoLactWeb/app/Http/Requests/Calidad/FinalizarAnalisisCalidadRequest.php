<?php

namespace App\Http\Requests\Calidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de los datos HTTP para confirmar (finalizar) un
 * análisis de calidad. El cálculo del resultado y la inmutabilidad tras
 * confirmar pertenecen al Use Case FinalizarAnalisisCalidad.
 */
class FinalizarAnalisisCalidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Etapa 10.1.1: ni analisis_id ni confirmado_por_user_id se validan
     * aquí. analisis_id llega por route model binding
     * (AnalisisCalidadController::finalizar recibe el AnalisisCalidadModel
     * ya resuelto) y confirmado_por_user_id es el actor: siempre
     * $request->user()->id, nunca un dato del cliente. No quedan campos
     * que validar en este Request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
