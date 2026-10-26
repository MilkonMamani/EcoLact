<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Produccion\Contracts\RecetasListadoQuery;
use App\Application\Produccion\RecetasListadoData;
use App\Domain\Produccion\Producto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\RecetaDetalleModel;
use App\Infrastructure\Persistence\Models\RecetaModel;

final class EloquentRecetasListadoQuery implements RecetasListadoQuery
{
    /**
     * @param  array{producto_id?: int|null, estado?: string|null, busqueda?: string|null, solo_vigente?: bool}  $filtros
     */
    public function obtener(array $filtros = []): RecetasListadoData
    {
        $consulta = RecetaModel::query()
            ->with(['producto', 'detalles.insumo'])
            ->withExists('produccionDetalles as en_uso')
            ->join('productos', 'productos.id', '=', 'recetas.producto_id')
            ->select('recetas.*')
            ->orderBy('productos.nombre')
            ->orderByDesc('recetas.version');

        if (! empty($filtros['solo_vigente'])) {
            $consulta->whereRaw(
                "recetas.id = (select vigente.id from recetas as vigente where vigente.producto_id = recetas.producto_id order by case when vigente.estado = 'ACTIVA' then 0 else 1 end, vigente.version desc limit 1)"
            );
        }

        if (! empty($filtros['producto_id'])) {
            $consulta->where('recetas.producto_id', (int) $filtros['producto_id']);
        }

        if (! empty($filtros['estado'])) {
            $consulta->where('recetas.estado', $filtros['estado']);
        }

        if (! empty($filtros['busqueda'])) {
            $busqueda = '%'.trim((string) $filtros['busqueda']).'%';
            $consulta->where(function ($sub) use ($busqueda): void {
                $sub->where('recetas.nombre', 'like', $busqueda)
                    ->orWhere('productos.nombre', 'like', $busqueda);
            });
        }

        $filas = $consulta->get()->map(fn (RecetaModel $receta): array => [
            'id' => $receta->id,
            'productoId' => $receta->producto_id,
            'producto' => $receta->producto->nombre,
            'productoNombreBase' => $receta->producto->nombre_base ?? $receta->producto->nombre,
            'tipo' => $receta->producto->tipo_producto,
            'presentacion' => $receta->producto->presentacion_cantidad !== null && $receta->producto->presentacion_unidad !== null
                ? Producto::formatoCantidad((float) $receta->producto->presentacion_cantidad).' '.$receta->producto->presentacion_unidad
                : null,
            'imagenUrl' => $receta->producto->imagenUrl(),
            'nombre' => $receta->nombre,
            'descripcion' => $receta->descripcion,
            'version' => (int) $receta->version,
            'rendimientoCantidad' => (float) $receta->rendimiento_cantidad,
            'rendimientoUnidad' => $receta->rendimiento_unidad,
            'estado' => $receta->estado,
            'enUso' => (bool) $receta->en_uso,
            'actualizada' => $receta->updated_at?->format('d/m/Y') ?? '—',
            'ingredientes' => $receta->detalles->sortBy('id')->map(fn (RecetaDetalleModel $detalle): array => [
                'insumoId' => $detalle->insumo_id,
                'insumo' => $detalle->insumo->nombre,
                'cantidad' => (float) $detalle->cantidad,
                'unidad' => $detalle->unidad,
                'observacion' => $detalle->observacion,
            ])->values()->all(),
        ])->all();

        return new RecetasListadoData(
            kpis: [
                'activas' => RecetaModel::where('estado', 'ACTIVA')->count(),
                'inactivas' => RecetaModel::where('estado', 'INACTIVA')->count(),
                'productosSinReceta' => ProductoModel::where('estado', 'ACTIVO')
                    ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('recetas')
                        ->whereColumn('recetas.producto_id', 'productos.id')
                        ->where('recetas.estado', 'ACTIVA'))
                    ->count(),
            ],
            filas: $filas,
        );
    }
}
