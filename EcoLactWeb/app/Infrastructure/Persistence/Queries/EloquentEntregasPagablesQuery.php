<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\EntregasPagablesQuery;
use App\Application\Pago\EntregaPagable;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EloquentEntregasPagablesQuery implements EntregasPagablesQuery
{
    public function porAsignar(?int $proveedorId = null): array
    {
        return EntregaModel::query()
            ->where('estado', '<>', EstadoEntrega::ANULADA->value)
            ->whereNotExists(fn (QueryBuilder $pagos) => $this->enPagoRealizado($pagos))
            ->whereNotExists(fn (QueryBuilder $liquidaciones) => $this->enLiquidacionAnterior($liquidaciones))
            ->when($proveedorId !== null, fn (Builder $query) => $query->where('proveedor_id', $proveedorId))
            ->with(['proveedor', 'analisisCalidad'])
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get()
            ->map(function (EntregaModel $entrega): EntregaPagable {
                $analisis = $entrega->analisisCalidad;
                $confirmado = $analisis !== null && $analisis->estado === EstadoAnalisis::CONFIRMADO->value && $analisis->resultado !== null;

                return new EntregaPagable(
                    entregaId: $entrega->id,
                    proveedorId: $entrega->proveedor_id,
                    proveedorNombre: trim($entrega->proveedor->nombres.' '.$entrega->proveedor->apellidos),
                    fechaHora: DateTimeImmutable::createFromInterface($entrega->fecha_hora),
                    litros: (float) $entrega->litros,
                    resultado: $confirmado ? ResultadoCalidad::from($analisis->resultado) : null,
                );
            })
            ->all();
    }

    public function estaEnPagoRealizado(int $entregaId): bool
    {
        return EntregaModel::query()
            ->whereKey($entregaId)
            ->where(fn (Builder $query) => $query
                ->whereExists(fn (QueryBuilder $pagos) => $this->enPagoRealizado($pagos))
                ->orWhereExists(fn (QueryBuilder $liquidaciones) => $this->enLiquidacionAnterior($liquidaciones)))
            ->exists();
    }

    public function proveedoresConPendientes(): array
    {
        return ObligacionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PROVEEDOR->value)
            ->where('estado', EstadoObligacionPago::PENDIENTE->value)
            ->whereNull('liquidacion_id')
            ->distinct()
            ->pluck('proveedor_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function enPagoRealizado(QueryBuilder $consulta): QueryBuilder
    {
        return $consulta->from('obligacion_pago_detalles as d')
            ->join('obligaciones_pago as o', 'o.id', '=', 'd.obligacion_pago_id')
            ->whereColumn('d.entrega_id', 'entregas.id')
            ->where('o.estado', EstadoObligacionPago::PAGADO->value)
            ->select('d.id');
    }

    private function enLiquidacionAnterior(QueryBuilder $consulta): QueryBuilder
    {
        return $consulta->from('valorizaciones_entrega as v')
            ->join('liquidacion_detalles as ld', 'ld.valorizacion_entrega_id', '=', 'v.id')
            ->join('liquidaciones as l', 'l.id', '=', 'ld.liquidacion_id')
            ->whereColumn('v.entrega_id', 'entregas.id')
            ->where('l.estado', '<>', 'ANULADA')
            ->select('v.id');
    }
}
