<?php

namespace App\Http\Requests\Zona;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Mover ubicación" del mapa de Zonas: la nueva coordenada del pin y, opcionalmente, la zona
 * que el navegador detectó (y el administrador confirmó) y la ruta destino cuando esa zona
 * tiene varias. La zona real la vuelve a calcular MoverProveedorDeZona en el servidor.
 */
class MoverProveedorDeZonaRequest extends FormRequest
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
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'zona_id' => ['nullable', 'integer'],
            'ruta_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitud.required' => 'Indica la nueva latitud.',
            'latitud.numeric' => 'La latitud debe ser un número.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90.',
            'longitud.required' => 'Indica la nueva longitud.',
            'longitud.numeric' => 'La longitud debe ser un número.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180.',
        ];
    }
}
