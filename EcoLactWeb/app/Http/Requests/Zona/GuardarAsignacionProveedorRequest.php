<?php

namespace App\Http\Requests\Zona;

use App\Infrastructure\Persistence\Models\RutaModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Valida el modal "Asignar proveedor a zona y ruta": zona, ruta (que debe
 * pertenecer a esa zona), punto de recolección y referencia opcional. El
 * proveedor llega por el parámetro de ruta {proveedor}.
 *
 * Este formulario existe para ubicar a cada proveedor en el mapa: latitud y
 * longitud son obligatorias AQUÍ (el esquema las sigue admitiendo nulas para
 * los proveedores todavía sin ubicar). Que el punto caiga en la zona de la
 * ruta lo verifica GuardarAsignacionProveedor con las delimitaciones.
 */
class GuardarAsignacionProveedorRequest extends FormRequest
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
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
            'ruta_id' => ['required', 'integer', 'exists:rutas,id'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'referencia_ubicacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zona_id.required' => 'Selecciona una zona.',
            'zona_id.exists' => 'La zona indicada no existe.',
            'ruta_id.required' => 'Selecciona una ruta.',
            'ruta_id.exists' => 'La ruta indicada no existe.',
            'latitud.required' => 'Marca en el mapa la ubicación del proveedor.',
            'latitud.numeric' => 'La latitud debe ser un número.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90.',
            'longitud.required' => 'Marca en el mapa la ubicación del proveedor.',
            'longitud.numeric' => 'La longitud debe ser un número.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180.',
            'referencia_ubicacion.max' => 'La referencia no puede superar los 255 caracteres.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['zona_id', 'ruta_id'])) {
                return;
            }

            $pertenece = RutaModel::query()
                ->whereKey($this->integer('ruta_id'))
                ->where('zona_id', $this->integer('zona_id'))
                ->exists();

            if (! $pertenece) {
                $validator->errors()->add('ruta_id', 'La ruta seleccionada no pertenece a la zona indicada.');
            }
        });
    }
}
