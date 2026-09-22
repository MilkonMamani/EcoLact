<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Liquidacion\Contracts\PagosListadoQuery;
use App\Application\Liquidacion\PagosFiltro;
use App\Application\Liquidacion\PagosListadoData;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * La tabla de Pagos representa la OBLIGACIÓN de pago (una fila por
 * liquidación), no únicamente los registros ya existentes en
 * pagos_proveedor: una liquidación PENDIENTE nunca tuvo un pago registrado
 * y de todos modos debe aparecer aquí para que el administrador sepa que
 * falta pagarla.
 */
final class EloquentPagosListadoQuery implements PagosListadoQuery
{
    public function obtener(PagosFiltro $filtro): PagosListadoData
    {
        $kpis = [
            'pendientesPago' => (int) LiquidacionModel::whereIn('estado', ['PENDIENTE', 'PARCIALMENTE_PAGADA'])->count(),
            'pagosRealizados' => (int) PagoProveedorModel::where('estado', '<>', 'ANULADO')->count(),
            'montoPendiente' => 0.0,
            'montoPagado' => 0.0,
        ];

        foreach (LiquidacionModel::whereIn('estado', ['PENDIENTE', 'PARCIALMENTE_PAGADA'])->get() as $liquidacion) {
            $pagado = (float) PagoProveedorModel::where('liquidacion_id', $liquidacion->id)
                ->where('estado', '<>', 'ANULADO')->sum('monto');
            $kpis['montoPendiente'] += round((float) $liquidacion->total_pagar - $pagado, 2);
        }

        $kpis['montoPagado'] = (float) PagoProveedorModel::where('estado', '<>', 'ANULADO')->sum('monto');
        $kpis['montoPendiente'] = round($kpis['montoPendiente'], 2);
        $kpis['montoPagado'] = round($kpis['montoPagado'], 2);

        $filas = $this->base($filtro)
            ->select('l.id', 'p.nombres', 'p.apellidos', 'p.dni', 'per.fecha_inicio', 'per.fecha_fin', 'l.total_pagar', 'l.estado')
            ->orderByDesc('per.fecha_inicio')
            ->orderBy('p.nombres')
            ->get()
            ->map(function (object $fila): array {
                $ultimoPago = PagoProveedorModel::where('liquidacion_id', $fila->id)
                    ->where('estado', '<>', 'ANULADO')
                    ->orderByDesc('fecha_pago')
                    ->first();

                return [
                    'liquidacionId' => (int) $fila->id,
                    'proveedorNombre' => trim($fila->nombres.' '.$fila->apellidos),
                    'proveedorDni' => $fila->dni,
                    'periodoDesde' => $fila->fecha_inicio,
                    'periodoHasta' => $fila->fecha_fin,
                    'monto' => (float) $fila->total_pagar,
                    'estadoPago' => $fila->estado,
                    'fechaPago' => $ultimoPago !== null ? CarbonImmutable::parse($ultimoPago->fecha_pago, config('app.timezone'))->format('d/m/Y H:i') : null,
                ];
            })
            ->all();

        $zonas = ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])->all();

        return new PagosListadoData(kpis: $kpis, zonas: $zonas, filas: $filas);
    }

    private function base(PagosFiltro $filtro): Builder
    {
        $query = LiquidacionModel::query()->from('liquidaciones as l')->toBase()
            ->join('proveedores as p', 'p.id', '=', 'l.proveedor_id')
            ->join('periodos_liquidacion as per', 'per.id', '=', 'l.periodo_id');

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $query->where(function (Builder $query) use ($busqueda, $filtro): void {
                $query->where('p.nombres', 'like', $busqueda)
                    ->orWhere('p.apellidos', 'like', $busqueda)
                    ->orWhere('p.dni', 'like', $busqueda)
                    ->orWhere('l.id', $filtro->busqueda);
            });
        }

        if ($filtro->estado !== null) {
            $query->where('l.estado', $filtro->estado);
        }

        if ($filtro->zonaId !== null) {
            $query->where('p.zona_id', $filtro->zonaId);
        }

        return $query;
    }
}
