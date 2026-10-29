<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Caja\CajaFiltro;
use App\Application\Caja\CajaResumen;
use App\Application\Caja\Contracts\CajaQuery;
use App\Application\Caja\MovimientoCajaFila;
use App\Domain\Caja\MovimientoCaja;
use App\Domain\Shared\Enums\CategoriaMovimientoCaja;
use App\Domain\Shared\Enums\TipoMovimientoCaja;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentCajaQuery implements CajaQuery
{
    /** Cómo se muestra el rol de quien originó el movimiento ("Registrado por"). */
    private const ROLES = [
        'ADMINISTRADOR' => 'Administrador',
        'VENTAS' => 'Personal de ventas',
        'ENCARGADO_PLANTA' => 'Encargado de planta',
        'ACOPIADOR' => 'Acopiador',
        'CALIDAD' => 'Analista de calidad',
        'PROVEEDOR' => 'Proveedor',
    ];

    /**
     * Una sola consulta agregada: saldo histórico y totales del día.
     */
    public function resumen(DateTimeImmutable $hoy): CajaResumen
    {
        $dia = CarbonImmutable::instance($hoy);
        $desde = $dia->startOfDay()->toDateTimeString();
        $hasta = $dia->endOfDay()->toDateTimeString();
        $entrada = TipoMovimientoCaja::ENTRADA->value;
        $salida = TipoMovimientoCaja::SALIDA->value;

        $fila = MovimientoCajaModel::query()->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? THEN monto ELSE -monto END), 0) as saldo', [$entrada])
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? AND fecha BETWEEN ? AND ? THEN monto ELSE 0 END), 0) as ingresos_hoy', [$entrada, $desde, $hasta])
            ->selectRaw('COALESCE(SUM(CASE WHEN tipo = ? AND fecha BETWEEN ? AND ? THEN monto ELSE 0 END), 0) as salidas_hoy', [$salida, $desde, $hasta])
            ->selectRaw('COALESCE(SUM(CASE WHEN fecha BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as movimientos_hoy', [$desde, $hasta])
            ->first();

        return new CajaResumen(
            saldo: round((float) $fila->saldo, 2),
            ingresosHoy: round((float) $fila->ingresos_hoy, 2),
            salidasHoy: round((float) $fila->salidas_hoy, 2),
            movimientosHoy: (int) $fila->movimientos_hoy,
        );
    }

    public function movimientos(CajaFiltro $filtro): LengthAwarePaginator
    {
        $query = $this->base()
            ->whereBetween('m.fecha', [$filtro->desde->toDateTimeString(), $filtro->hasta->toDateTimeString()]);

        if ($filtro->tipo !== null) {
            $query->where('m.tipo', $filtro->tipo->value);
        }

        if ($filtro->categoria !== null) {
            $query->where('m.categoria', $filtro->categoria->value);
        }

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $query->where(function (Builder $query) use ($busqueda): void {
                $query->where('m.concepto', 'like', $busqueda)
                    ->orWhere('m.descripcion', 'like', $busqueda)
                    ->orWhere('m.referencia', 'like', $busqueda)
                    ->orWhere('u.username', 'like', $busqueda);
            });
        }

        return $query->orderByDesc('m.fecha')
            ->orderByDesc('m.id')
            ->paginate($filtro->porPagina)
            ->withQueryString()
            ->through(fn (object $fila): MovimientoCajaFila => $this->fila($fila));
    }

    public function buscar(int $id): ?MovimientoCajaFila
    {
        // La compra de origen: directa en las salidas históricas (COMPRA_INSUMOS #compra)
        // o a través de su pago (PAGO_COMPRA #pago -> pagos_compra.compra_insumo_id).
        $fila = $this->base()
            ->leftJoin('pagos_compra as pc', function ($join): void {
                $join->on('pc.id', '=', 'm.referencia_id')
                    ->where('m.referencia_tipo', '=', MovimientoCaja::REFERENCIA_PAGO_COMPRA);
            })
            ->selectRaw('CASE WHEN m.referencia_tipo = ? THEN m.referencia_id ELSE pc.compra_insumo_id END as compra_id', [MovimientoCaja::REFERENCIA_COMPRA_INSUMOS])
            ->where('m.id', $id)
            ->first();

        if ($fila !== null && $fila->compra_id !== null) {
            $fila->comprobante_id = DB::table('comprobantes_compra')->where('compra_insumo_id', $fila->compra_id)->orderBy('id')->value('id');
        }

        return $fila !== null ? $this->fila($fila) : null;
    }

    private function base(): Builder
    {
        return MovimientoCajaModel::query()->from('movimientos_caja as m')->toBase()
            ->leftJoin('users as u', 'u.id', '=', 'm.registrado_por_user_id')
            ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->select('m.*', 'u.username', 'r.nombre as rol_nombre');
    }

    private function fila(object $fila): MovimientoCajaFila
    {
        $detalle = is_string($fila->detalle) ? json_decode($fila->detalle, true) : null;
        $rol = self::ROLES[$fila->rol_nombre ?? ''] ?? null;

        return new MovimientoCajaFila(
            id: (int) $fila->id,
            tipo: TipoMovimientoCaja::from($fila->tipo),
            categoria: CategoriaMovimientoCaja::from($fila->categoria),
            monto: round((float) $fila->monto, 2),
            concepto: $fila->concepto,
            descripcion: $fila->descripcion,
            referencia: $fila->referencia,
            detalle: is_array($detalle) ? $detalle : [],
            referenciaTipo: $fila->referencia_tipo,
            referenciaId: (int) $fila->referencia_id,
            registradoPor: $rol !== null ? $rol.' · '.$fila->username : (string) $fila->username,
            fecha: CarbonImmutable::parse($fila->fecha, config('app.timezone')),
            compraId: isset($fila->compra_id) ? (int) $fila->compra_id : null,
            comprobanteId: isset($fila->comprobante_id) ? (int) $fila->comprobante_id : null,
        );
    }
}
