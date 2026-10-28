<?php

namespace App\Http\Requests\Zona;

use App\Domain\Zona\ZonaRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreZonaRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:100', 'unique:zonas,nombre'],
            'descripcion' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe una zona con ese nombre.',
        ];
    }

    /** "Collana1" cuando ya existe "Collana 1" no choca con el UNIQUE, pero es la misma zona. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('nombre')) {
                return;
            }

            $equivalente = app(ZonaRepository::class)->buscarEquivalente($this->string('nombre')->toString());

            if ($equivalente !== null) {
                $validator->errors()->add('nombre', "Ya existe una zona equivalente con el nombre {$equivalente->nombre()}.");
            }
        });
    }
}
