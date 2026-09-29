<?php

namespace App\Http\Requests\Zona;

use App\Domain\Zona\DelimitacionZona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * "Editar zona": nombre, descripción y estado, y (desde "Editar
 * delimitación") el color del mapa y el polígono GeoJSON. El polígono se
 * valida con las mismas reglas del dominio (mínimo 3 puntos, sin lados que
 * se crucen, JSON válido) para que el error aparezca bajo el mapa.
 */
class UpdateZonaRequest extends FormRequest
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
            'nombre' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('zonas', 'nombre')->ignore($this->route('zona')),
            ],
            'descripcion' => ['sometimes', 'nullable', 'string'],
            'estado' => ['sometimes', 'string', Rule::in(['ACTIVO', 'INACTIVO'])],
            'color_mapa' => ['sometimes', 'required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'poligono_geojson' => ['sometimes', 'nullable', 'string', 'max:65000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe otra zona con ese nombre.',
            'color_mapa.regex' => 'El color del mapa debe tener el formato #RRGGBB.',
            'poligono_geojson.max' => 'La delimitación tiene demasiados puntos.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $geojson = $this->input('poligono_geojson');

            if ($validator->errors()->has('poligono_geojson') || ! is_string($geojson) || trim($geojson) === '') {
                return;
            }

            try {
                DelimitacionZona::desdeGeoJson($geojson);
            } catch (InvalidArgumentException $error) {
                $validator->errors()->add('poligono_geojson', $error->getMessage());
            }
        });
    }
}
