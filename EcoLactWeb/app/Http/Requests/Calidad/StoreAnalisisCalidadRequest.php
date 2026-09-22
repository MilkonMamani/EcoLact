<?php

namespace App\Http\Requests\Calidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el formato de los datos HTTP para registrar (o revisar mientras
 * esté BORRADOR) un análisis de calidad a partir de valores ya extraídos.
 *
 * NO implementa OCR ni decide ACEPTADA/OBSERVADA/RECHAZADA: solo valida que
 * los valores recibidos sean numéricos. La clasificación pertenece a
 * CalcularResultadoCalidad (Application/Domain).
 */
class StoreAnalisisCalidadRequest extends FormRequest
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
            'valores' => ['required', 'array', 'min:1'],
            'valores.*' => ['nullable', 'numeric'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entrega_id.exists' => 'La entrega indicada no existe.',
            'valores.required' => 'Debe registrar al menos un valor de parámetro de calidad.',
            'valores.*.numeric' => 'Cada valor de parámetro debe ser numérico.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La imagen debe tener formato jpg, jpeg o png.',
            'imagen.max' => 'La imagen no debe superar los 5 MB.',
        ];
    }
}
