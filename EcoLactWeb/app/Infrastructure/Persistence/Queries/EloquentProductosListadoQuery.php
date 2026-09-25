<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Produccion\Contracts\ProductosListadoQuery;
use App\Application\Produccion\ProductosFiltro;
use App\Application\Produccion\ProductosListadoData;
use App\Domain\Produccion\Producto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final class EloquentProductosListadoQuery implements ProductosListadoQuery
{
    /**
     * Saldo de un producto: suma con signo de sus movimientos (no existe una
     * tabla de saldo). Requiere que la consulta externa use el alias `p`.
     */
    public const SALDO_SQL = "SELECT COALESCE(SUM(CASE WHEN m.tipo IN ('INGRESO_PRODUCCION', 'AJUSTE_ENTRADA') THEN m.cantidad ELSE -m.cantidad END), 0) FROM producto_movimientos AS m WHERE m.producto_id = p.id";

    private const RECETA_ACTIVA_SQL = "SELECT COUNT(*) FROM recetas AS r WHERE r.producto_id = p.id AND r.estado = 'ACTIVA'";

    private const RECETAS_SQL = 'SELECT COUNT(*) FROM recetas AS r WHERE r.producto_id = p.id';

    private const ULTIMA_PRODUCCION_SQL = "SELECT MAX(l.fecha_hora_fin) FROM produccion_detalles AS d INNER JOIN lotes_produccion AS l ON l.id = d.lote_produccion_id WHERE d.producto_id = p.id AND l.estado = 'FINALIZADO'";

    public function obtener(ProductosFiltro $filtro): ProductosListadoData
    {
        $todos = ProductoModel::query()->from('productos as p')->toBase();

        $kpis = [
            'total' => (int) (clone $todos)->count(),
            'disponibles' => (int) (clone $todos)->whereRaw('('.self::SALDO_SQL.') > 0')->count(),
            'sinStock' => (int) (clone $todos)->whereRaw('('.self::SALDO_SQL.') <= 0')->count(),
            'sinReceta' => (int) (clone $todos)->whereRaw('('.self::RECETA_ACTIVA_SQL.') = 0')->count(),
        ];

        $consulta = $this->base($filtro)
            ->select('p.id', 'p.nombre', 'p.nombre_base', 'p.tipo_producto', 'p.presentacion_cantidad', 'p.presentacion_unidad', 'p.imagen', 'p.unidad_medida', 'p.estado')
            ->selectRaw('('.self::SALDO_SQL.') as stock_actual')
            ->selectRaw('('.self::RECETA_ACTIVA_SQL.') as recetas_activas')
            ->selectRaw('('.self::RECETAS_SQL.') as recetas_total')
            ->selectRaw('('.self::ULTIMA_PRODUCCION_SQL.') as ultima_produccion')
            ->orderBy('p.nombre');

        $filas = $consulta->get()->map(function (object $fila): array {
            $stock = max(0.0, round((float) $fila->stock_actual, 2));
            $presentacion = $fila->presentacion_cantidad !== null && $fila->presentacion_unidad !== null
                ? Producto::formatoCantidad((float) $fila->presentacion_cantidad).' '.$fila->presentacion_unidad
                : null;

            return [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'nombreBase' => $fila->nombre_base ?? $fila->nombre,
                'tipo' => $fila->tipo_producto,
                'presentacion' => $presentacion,
                'imagenUrl' => $fila->imagen ? asset('storage/'.ltrim($fila->imagen, '/')) : null,
                'unidadMedida' => $fila->unidad_medida,
                'stockActual' => $stock,
                'recetaEstado' => (int) $fila->recetas_activas > 0 ? 'CONFIGURADA' : ((int) $fila->recetas_total > 0 ? 'INACTIVA' : 'SIN_RECETA'),
                'ultimaProduccion' => $fila->ultima_produccion !== null
                    ? CarbonImmutable::parse($fila->ultima_produccion, config('app.timezone'))->format('d/m/Y')
                    : null,
                'estado' => $fila->estado !== 'ACTIVO' ? 'INACTIVO' : ($stock > 0 ? 'DISPONIBLE' : 'SIN_STOCK'),
            ];
        })->all();

        return new ProductosListadoData(kpis: $kpis, filas: $filas);
    }

    private function base(ProductosFiltro $filtro): Builder
    {
        $query = ProductoModel::query()->from('productos as p')->toBase();

        if ($filtro->busqueda !== null) {
            $query->where(function ($sub) use ($filtro): void {
                $sub->where('p.nombre', 'like', '%'.$filtro->busqueda.'%')
                    ->orWhere('p.tipo_producto', 'like', '%'.$filtro->busqueda.'%');
            });
        }

        if ($filtro->estado === ProductosFiltro::DISPONIBLE) {
            $query->whereRaw('('.self::SALDO_SQL.') > 0');
        } elseif ($filtro->estado === ProductosFiltro::SIN_STOCK) {
            $query->whereRaw('('.self::SALDO_SQL.') <= 0');
        }

        return $query;
    }
}
