<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Ventas\Contracts\VentasListadoQuery;
use App\Application\Ventas\VentasFiltro;
use App\Application\Ventas\VentasListadoData;
use App\Domain\Ventas\VentaProducto;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

final class EloquentVentasListadoQuery implements VentasListadoQuery
{
    public function obtener(VentasFiltro $filtro): VentasListadoData
    {
        $resumen = $this->base($filtro)
            ->selectRaw('COUNT(*) as ventas')
            ->selectRaw('COALESCE(SUM(v.importe_total), 0) as total_vendido')
            ->selectRaw('COALESCE(SUM(v.cantidad), 0) as productos_vendidos')
            ->first();

        // CONCAT() no es portable entre SQLite (tests) y MySQL (producción);
        // se calculan los clientes distintos en PHP para evitar acoplar la
        // consulta a un motor concreto.
        $clientes = $this->base($filtro)->select('v.tipo_cliente', 'v.proveedor_id', 'v.comprador_id')->get();
        $compradoresUnicos = $clientes
            ->map(fn (object $fila): ?string => match (true) {
                $fila->tipo_cliente === 'PROVEEDOR' => 'P-'.$fila->proveedor_id,
                $fila->comprador_id !== null => 'C-'.$fila->comprador_id,
                default => null,
            })
            ->filter()
            ->unique()
            ->count();

        $kpis = [
            'ventas' => (int) $resumen->ventas,
            'totalVendido' => round((float) $resumen->total_vendido, 2),
            'productosVendidos' => round((float) $resumen->productos_vendidos, 2),
            'compradores' => $compradoresUnicos,
        ];

        $filas = $this->base($filtro)
            ->select(
                'v.id',
                'v.fecha',
                'v.tipo_cliente',
                'v.cantidad',
                'v.importe_total',
                'v.estado',
                'v.forma_pago',
                'p.nombre as producto_nombre',
                'pr.nombres as proveedor_nombres',
                'pr.apellidos as proveedor_apellidos',
                'c.nombre as comprador_nombre',
            )
            ->orderByDesc('v.fecha')
            ->orderByDesc('v.id')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'codigo' => VentaProducto::codigo((int) $fila->id),
                'fecha' => CarbonImmutable::parse($fila->fecha, config('app.timezone'))->format('d/m/Y H:i'),
                'fechaCorta' => self::fechaCorta(CarbonImmutable::parse($fila->fecha, config('app.timezone'))),
                'productoNombre' => $fila->producto_nombre,
                'clienteNombre' => $fila->tipo_cliente === 'PROVEEDOR'
                    ? trim($fila->proveedor_nombres.' '.$fila->proveedor_apellidos)
                    : ($fila->comprador_nombre ?? 'Público general'),
                'clienteTipo' => $fila->tipo_cliente,
                'cantidad' => (float) $fila->cantidad,
                'importeTotal' => (float) $fila->importe_total,
                'formaPago' => $fila->forma_pago,
                'estado' => $fila->estado,
            ])
            ->all();

        return new VentasListadoData(kpis: $kpis, filas: $filas);
    }

    /** "26 oct. · 14:32" */
    private static function fechaCorta(CarbonImmutable $fecha): string
    {
        $meses = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'set.', 'oct.', 'nov.', 'dic.'];

        return $fecha->format('j').' '.$meses[$fecha->month - 1].' · '.$fecha->format('H:i');
    }

    private function base(VentasFiltro $filtro): Builder
    {
        $query = VentaProductoModel::query()->from('ventas_producto as v')->toBase()
            ->join('productos as p', 'p.id', '=', 'v.producto_id')
            ->leftJoin('proveedores as pr', 'pr.id', '=', 'v.proveedor_id')
            ->leftJoin('compradores as c', 'c.id', '=', 'v.comprador_id')
            ->whereBetween('v.fecha', [$filtro->fechaDesde->toDateTimeString(), $filtro->fechaHasta->toDateTimeString()]);

        if ($filtro->vendedorId !== null) {
            $query->where('v.registrado_por_user_id', $filtro->vendedorId);
        }

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $ventaId = preg_match('/^v-?0*(\d+)$/i', $filtro->busqueda, $codigo) === 1 ? (int) $codigo[1] : null;
            $query->where(function (Builder $query) use ($busqueda, $ventaId): void {
                $query->where('p.nombre', 'like', $busqueda)
                    ->when($ventaId !== null, fn (Builder $query) => $query->orWhere('v.id', $ventaId))
                    ->orWhere('c.nombre', 'like', $busqueda)
                    ->orWhere('pr.nombres', 'like', $busqueda)
                    ->orWhere('pr.apellidos', 'like', $busqueda);
            });
        }

        if ($filtro->tipoCliente !== null) {
            $query->where('v.tipo_cliente', $filtro->tipoCliente);
        }

        if ($filtro->formaPago !== null) {
            $query->where('v.forma_pago', $filtro->formaPago);
        }

        if ($filtro->estado !== null) {
            $query->where('v.estado', $filtro->estado);
        }

        return $query;
    }
}
