<?php

namespace App\Http\Requests\Produccion;

use App\Infrastructure\Persistence\Models\CategoriaProductoModel;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Nueva categoría de producto desde el combobox del modal de receta. El
 * duplicado se compara sin mayúsculas ni espacios extra ("quesos " = "Quesos").
 */
class StoreCategoriaProductoRequest extends FormRequest
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
                'required',
                'string',
                'max:60',
                function (string $atributo, mixed $valor, Closure $fallar): void {
                    if (is_string($valor) && CategoriaProductoModel::existeNombre($valor)) {
                        $fallar('Ya existe la categoría '.CategoriaProductoModel::limpiar($valor).'.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escriba el nombre de la categoría.',
            'nombre.max' => 'El nombre de la categoría no puede superar 60 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => CategoriaProductoModel::limpiar($this->input('nombre'))]);
        }
    }
}
