<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\SaldosObligacionesQuery;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Una consulta por indicador. El saldo de cada obligación se calcula en SQL
 * (DECIMAL) y nunca es negativo. Una obligación PAGADO sin filas en `pagos`
 * (registros anteriores al libro de pagos) se considera saldada.
 */
final class EloquentSaldosObligacionesQuery implements SaldosObligacionesQuery
{
    private const SALDO = "CASE WHEN o.estado = 'PAGADO' AND COALESCE(p.total, 0) = 0 THEN 0"
        .' WHEN o.monto_calculado - COALESCE(p.al_corte, 0) > 0 THEN o.monto_calculado - COALESCE(p.al_corte, 0) ELSE 0 END';

    public function saldoAl(TipoBeneficiarioPago $tipo, DateTimeImmutable $corte): array
    {
        $dia = $corte->format('Y-m-d');
        $saldo = self::SALDO;

        $consulta = $this->obligaciones($tipo, $dia);
        $tipo === TipoBeneficiarioPago::PERSONAL
            ? $consulta->whereDate('o.periodo_fin', '<', $dia)
            : $consulta->whereDate('o.periodo_inicio', '<=', $dia);

        $fila = $consulta
            ->selectRaw("COALESCE(SUM({$saldo}), 0) as pendiente")
            ->selectRaw("COALESCE(SUM(CASE WHEN ({$saldo}) > 0 THEN 1 ELSE 0 END), 0) as obligaciones")
            ->selectRaw("COALESCE(SUM(CASE WHEN DATE(o.fecha_pago_programada) < ? THEN {$saldo} ELSE 0 END), 0) as vencido", [$dia])
            ->selectRaw("COALESCE(SUM(CASE WHEN DATE(o.fecha_pago_programada) < ? AND ({$saldo}) > 0 THEN 1 ELSE 0 END), 0) as vencidas", [$dia])
            ->first();

        return [
            'pendiente' => round((float) $fila->pendiente, 2),
            'obligaciones' => (int) $fila->obligaciones,
            'vencido' => round((float) $fila->vencido, 2),
            'vencidas' => (int) $fila->vencidas,
        ];
    }

    public function programadoEntre(TipoBeneficiarioPago $tipo, DateTimeImmutable $desde, DateTimeImmutable $hasta, DateTimeImmutable $corte): array
    {
        $saldo = self::SALDO;

        $fila = $this->obligaciones($tipo, $corte->format('Y-m-d'))
            ->whereDate('o.fecha_pago_programada', '>=', $desde->format('Y-m-d'))
            ->whereDate('o.fecha_pago_programada', '<=', $hasta->format('Y-m-d'))
            ->selectRaw('COALESCE(SUM(o.monto_calculado), 0) as importe, COUNT(*) as obligaciones')
            ->selectRaw("COALESCE(SUM({$saldo}), 0) as por_pagar")
            ->first();

        $importe = round((float) $fila->importe, 2);
        $porPagar = round((float) $fila->por_pagar, 2);

        return [
            'importe' => $importe,
            'pagado' => round($importe - $porPagar, 2),
            'porPagar' => $porPagar,
            'obligaciones' => (int) $fila->obligaciones,
        ];
    }

    /** Obligaciones reconocidas del tipo (sin NO_PAGABLE ni meses EN_CURSO) con lo pagado hasta el corte. */
    private function obligaciones(TipoBeneficiarioPago $tipo, string $corte): Builder
    {
        $pagos = DB::table('pagos')
            ->select('obligacion_pago_id')
            ->selectRaw('SUM(monto) as total')
            ->selectRaw('SUM(CASE WHEN DATE(fecha_pago) <= ? THEN monto ELSE 0 END) as al_corte', [$corte])
            ->groupBy('obligacion_pago_id');

        return DB::table('obligaciones_pago as o')
            ->leftJoinSub($pagos, 'p', 'p.obligacion_pago_id', '=', 'o.id')
            ->where('o.tipo_beneficiario', $tipo->value)
            ->whereIn('o.estado', [EstadoObligacionPago::PENDIENTE->value, EstadoObligacionPago::PAGADO->value]);
    }
}
