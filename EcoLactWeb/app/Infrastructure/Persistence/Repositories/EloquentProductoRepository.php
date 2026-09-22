<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\Producto;
use App\Domain\Produccion\ProductoRepository;
use App\Infrastructure\Persistence\Models\ProductoModel;

class EloquentProductoRepository implements ProductoRepository
{
    public function guardar(Producto $producto): Producto
    {
        $model = ProductoModel::create($this->toModelData($producto));

        return $this->toEntity($model);
    }

    public function actualizar(Producto $producto): Producto
    {
        $model = ProductoModel::findOrFail($producto->id());
        $model->fill($this->toModelData($producto));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Producto
    {
        $model = ProductoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorNombre(string $nombre): ?Producto
    {
        $model = ProductoModel::where('nombre', $nombre)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Producto[]
     */
    public function listarActivos(): array
    {
        return ProductoModel::where('estado', 'ACTIVO')
            ->get()
            ->map(fn (ProductoModel $model): Producto => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Producto[]
     */
    public function listar(): array
    {
        return ProductoModel::orderBy('nombre')
            ->get()
            ->map(fn (ProductoModel $model): Producto => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Producto[]
     */
    public function listarConRecetaActiva(): array
    {
        return ProductoModel::where('estado', 'ACTIVO')
            ->whereHas('recetas', fn ($consulta) => $consulta->where('estado', 'ACTIVA'))
            ->orderBy('nombre')
            ->get()
            ->map(fn (ProductoModel $model): Producto => $this->toEntity($model))
            ->all();
    }

    public function contarConImagen(string $ruta): int
    {
        return ProductoModel::where('imagen', $ruta)->count();
    }

    private function toEntity(ProductoModel $model): Producto
    {
        return new Producto(
            id: $model->id,
            nombre: $model->nombre,
            unidadMedida: $model->unidad_medida,
            estado: $model->estado,
            nombreBase: $model->nombre_base,
            tipoProducto: $model->tipo_producto,
            presentacionCantidad: $model->presentacion_cantidad !== null ? (float) $model->presentacion_cantidad : null,
            presentacionUnidad: $model->presentacion_unidad,
            imagen: $model->imagen,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Producto $producto): array
    {
        return [
            'nombre' => $producto->nombre(),
            'nombre_base' => $producto->nombreBase(),
            'tipo_producto' => $producto->tipoProducto(),
            'presentacion_cantidad' => $producto->presentacionCantidad(),
            'presentacion_unidad' => $producto->presentacionUnidad(),
            'imagen' => $producto->imagen(),
            'unidad_medida' => $producto->unidadMedida(),
            'estado' => $producto->estado(),
        ];
    }
}
