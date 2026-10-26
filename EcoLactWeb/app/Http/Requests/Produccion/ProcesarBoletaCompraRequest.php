<?php

namespace App\Http\Requests\Produccion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Foto de la boleta de compra que se envía a leer por OCR. Solo valida el
 * archivo; no registra ninguna compra.
 */
class ProcesarBoletaCompraRequest extends FormRequest
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
            'imagen' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'imagen.required' => 'Toma o selecciona la foto de la boleta.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La foto debe ser jpg, jpeg, png o webp.',
            'imagen.max' => 'La foto no debe superar los 5 MB.',
            'imagen.uploaded' => 'La foto no se pudo subir. Intenta con una imagen más liviana.',
        ];
    }
}
