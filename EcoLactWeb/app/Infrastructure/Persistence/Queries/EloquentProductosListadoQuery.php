<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Produccion\Contracts\ProductosListadoQuery;
use App\Application\Produccion\ProductosFiltro;
use App\Application\Produccion\ProductosListadoData;
use App\Domain\Produccion\Producto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoPrecioModel;
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

    public function obtener(ProductosFiltro $filtro): ProductosListadoData
    {
        $consulta = $this->base($filtro)
            ->leftJoin('categorias_productos as c', 'c.id', '=', 'p.categoria_id')
            ->select('p.id', 'p.nombre', 'p.nombre_base', 'p.tipo_producto', 'p.presentacion_cantidad', 'p.presentacion_unidad', 'p.imagen', 'p.unidad_medida', 'p.estado')
            ->addSelect('c.nombre as categoria')
            ->selectRaw('('.self::SALDO_SQL.') as stock_actual')
            ->selectRaw('('.self::RECETA_ACTIVA_SQL.') as recetas_activas')
            ->selectRaw('('.self::RECETAS_SQL.') as recetas_total')
            ->orderBy('p.nombre');

        $productos = $consulta->get();
        $precios = $this->preciosVigentes($productos->pluck('id')->all());

        $filas = $productos->map(function (object $fila) use ($precios): array {
            $stock = max(0.0, round((float) $fila->stock_actual, 2));
            $presentacion = $fila->presentacion_cantidad !== null && $fila->presentacion_unidad !== null
                ? Producto::formatoCantidad((float) $fila->presentacion_cantidad).' '.$fila->presentacion_unidad
                : null;

            return [
                'id' => (int) $fila->id,
                'nombre' => $fila->nombre,
                'nombreBase' => $fila->nombre_base ?? $fila->nombre,
                'categoria' => $fila->categoria ?? $fila->tipo_producto,
                'presentacion' => $presentacion,
                'imagenUrl' => $fila->imagen ? asset('storage/'.ltrim($fila->imagen, '/')) : null,
                'unidadMedida' => $fila->unidad_medida,
                'stockActual' => $stock,
                'recetaEstado' => (int) $fila->recetas_activas > 0 ? 'CONFIGURADA' : ((int) $fila->recetas_total > 0 ? 'INACTIVA' : 'SIN_RECETA'),
                'precios' => $precios[(int) $fila->id] ?? ['PROVEEDOR' => null, 'PUBLICO' => null, 'MAYORISTA' => null, 'cantidadMinimaMayorista' => null],
                'estado' => $fila->estado !== 'ACTIVO' ? 'INACTIVO' : ($stock > 0 ? 'DISPONIBLE' : 'SIN_STOCK'),
            ];
        })->all();

        return new ProductosListadoData(filas: $filas);
    }

    /**
     * Precio vigente hoy por tipo de cliente de cada producto (el más reciente
     * si quedara más de uno ACTIVO, igual que ProductoPrecioRepository::buscarVigente()).
     *
     * @param  list<int>  $productoIds
     * @return array<int, array{PROVEEDOR: ?float, PUBLICO: ?float, MAYORISTA: ?float, cantidadMinimaMayorista: ?float}>
     */
    private function preciosVigentes(array $productoIds): array
    {
        $hoy = CarbonImmutable::today()->toDateString();
        $precios = [];

        $filas = ProductoPrecioModel::query()
            ->whereIn('producto_id', $productoIds)
            ->where('estado', 'ACTIVO')
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $hoy))
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get(['producto_id', 'tipo_cliente', 'precio', 'cantidad_minima']);

        foreach ($filas as $fila) {
            $precios[$fila->producto_id] ??= ['PROVEEDOR' => null, 'PUBLICO' => null, 'MAYORISTA' => null, 'cantidadMinimaMayorista' => null];
            $precios[$fila->producto_id][$fila->tipo_cliente] = (float) $fila->precio;

            if ($fila->tipo_cliente === 'MAYORISTA') {
                $precios[$fila->producto_id]['cantidadMinimaMayorista'] = $fila->cantidad_minima !== null ? (float) $fila->cantidad_minima : null;
            }
        }

        return $precios;
    }

    private function base(ProductosFiltro $filtro): Builder
    {
        $query = ProductoModel::query()->from('productos as p')->toBase();

        if ($filtro->busqueda !== null) {
            $query->where(function ($sub) use ($filtro): void {
                $sub->where('p.nombre', 'like', '%'.$filtro->busqueda.'%')
                    ->orWhere('p.tipo_producto', 'like', '%'.$filtro->busqueda.'%')
                    ->orWhereIn('p.categoria_id', fn ($c) => $c->select('id')->from('categorias_productos')->where('nombre', 'like', '%'.$filtro->busqueda.'%'));
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
