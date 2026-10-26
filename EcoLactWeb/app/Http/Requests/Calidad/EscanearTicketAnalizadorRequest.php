<?php

namespace App\Http\Requests\Calidad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Foto del ticket del analizador que se envía a leer por OCR. Solo valida el
 * archivo y la entrega; no registra ningún análisis.
 */
class EscanearTicketAnalizadorRequest extends FormRequest
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
            'imagen' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entrega_id.exists' => 'La entrega indicada no existe.',
            'imagen.required' => 'Toma o selecciona la foto del ticket.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La foto debe ser jpg, jpeg, png o webp.',
            'imagen.max' => 'La foto no debe superar los 5 MB.',
            'imagen.uploaded' => 'La foto no se pudo subir. Intenta con una imagen más liviana.',
        ];
    }
}
