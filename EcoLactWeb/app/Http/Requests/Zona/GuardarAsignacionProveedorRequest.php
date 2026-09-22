<?php

namespace App\Http\Requests\Zona;

use App\Infrastructure\Persistence\Models\RutaModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Valida el modal "Asignar proveedor a zona y ruta": zona, ruta (que debe
 * pertenecer a esa zona), punto de recolección (latitud/longitud, siempre
 * juntas) y referencia opcional. El proveedor llega por el parámetro de
 * ruta {proveedor}.
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
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
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
            'latitud.numeric' => 'La latitud debe ser un número.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90.',
            'latitud.required_with' => 'Indica también la latitud: la latitud y la longitud van juntas.',
            'longitud.numeric' => 'La longitud debe ser un número.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180.',
            'longitud.required_with' => 'Indica también la longitud: la latitud y la longitud van juntas.',
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
