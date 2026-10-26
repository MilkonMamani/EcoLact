<?php

namespace App\Http\Requests\Produccion;

use App\Domain\Produccion\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el formulario de receta (nueva, editar o nueva versión). Solo valida
 * formato: que la unidad sea compatible con el insumo, que no haya duplicados
 * o que la receta esté en uso lo decide el Use Case.
 *
 * El nombre de la receta NO es un campo: se genera solo a partir del producto.
 * La imagen (opcional) se valida por su tipo MIME real y su tamaño (2 MB).
 */
class StoreRecetaRequest extends FormRequest
{
    public const MAX_IMAGEN_KB = 2048;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $esNueva = $this->routeIs('*.recetas.store');
        $sinProductoExistente = $esNueva && ! $this->filled('producto_existente_id');

        return [
            'producto_existente_id' => $esNueva ? ['nullable', 'integer', 'exists:productos,id'] : ['prohibited'],
            'categoria_id' => $sinProductoExistente ? ['required', 'integer', Rule::exists('categorias_productos', 'id')->where('estado', 'ACTIVA')] : ['prohibited'],
            'nombre_producto' => $sinProductoExistente ? ['required', 'string', 'max:100'] : ['prohibited'],
            'presentacion_cantidad' => $sinProductoExistente ? ['required', 'numeric', 'gt:0', 'max:999999'] : ['prohibited'],
            'presentacion_unidad' => $sinProductoExistente ? ['required', 'string', Rule::in(UnidadMedida::codigos())] : ['prohibited'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'imagen' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_IMAGEN_KB],
            'ingredientes' => ['required', 'array', 'min:1'],
            'ingredientes.*.insumo_id' => ['required', 'integer', 'exists:insumos,id', 'distinct'],
            'ingredientes.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'ingredientes.*.unidad' => ['required', 'string', Rule::in(UnidadMedida::codigos())],
            'ingredientes.*.observacion' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_id.required' => 'Elija la categoría del producto.',
            'categoria_id.integer' => 'Elija una categoría de la lista o guarde la nueva categoría.',
            'categoria_id.exists' => 'La categoría elegida no existe o no está activa.',
            'nombre_producto.required' => 'Escriba el nombre del producto.',
            'presentacion_cantidad.required' => 'Indique la presentación (por ejemplo 1).',
            'presentacion_cantidad.gt' => 'La presentación debe ser mayor que cero.',
            'presentacion_unidad.required' => 'Elija la unidad de la presentación.',
            'presentacion_unidad.in' => 'Elija una unidad válida: kg, g, L, ml o unidad.',
            'imagen.mimetypes' => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
            'imagen.file' => 'No se pudo leer la imagen. Intente con otra.',
            'ingredientes.required' => 'Agregue al menos un ingrediente.',
            'ingredientes.min' => 'Agregue al menos un ingrediente.',
            'ingredientes.*.insumo_id.required' => 'Elija el insumo de cada ingrediente.',
            'ingredientes.*.insumo_id.distinct' => 'Un insumo está repetido: use una sola línea por ingrediente.',
            'ingredientes.*.cantidad.gt' => 'La cantidad de cada ingrediente debe ser mayor que cero.',
            'ingredientes.*.cantidad.required' => 'Indique la cantidad de cada ingrediente.',
            'ingredientes.*.unidad.in' => 'Elija una unidad válida en cada ingrediente.',
        ];
    }
}
