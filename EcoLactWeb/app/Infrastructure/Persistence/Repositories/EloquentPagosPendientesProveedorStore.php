<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Pago\Contracts\PagosPendientesProveedorStore;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\AjusteLiquidacionModel;
use App\Infrastructure\Persistence\Models\AuditoriaModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoDetalleModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentPagosPendientesProveedorStore implements PagosPendientesProveedorStore
{
    public function liberarPendientes(int $proveedorId): array
    {
        ProveedorModel::whereKey($proveedorId)->lockForUpdate()->firstOrFail();
        $pendientes = $this->pendientesPropios($proveedorId)->with(['detalles', 'pago'])->orderBy('id')->lockForUpdate()->get();
        $existentes = [];
        foreach ($pendientes->groupBy(fn (ObligacionPagoModel $o): string => PeriodoPago::semanaQueContiene(DateTimeImmutable::createFromInterface($o->periodo_inicio))->clave()) as $grupo) {
            if ($grupo->count() > 1 && $grupo->contains(fn (ObligacionPagoModel $o): bool => $o->detalles->isEmpty() && empty($o->base_calculo['entregas']))) {
                throw new DomainException('Hay pendientes duplicados sin detalle de entregas verificable. Ejecute pagos:auditar-semanas y revise sus documentos antes de consolidar.');
            }
        }

        foreach ($pendientes as $pendiente) {
            if ($pendiente->pago !== null) {
                throw new DomainException('La obligación #'.$pendiente->id.' tiene un pago registrado pero figura pendiente. Requiere revisión.');
            }
            $clave = PeriodoPago::semanaQueContiene(DateTimeImmutable::createFromInterface($pendiente->periodo_inicio))->clave();
            if (isset($existentes[$clave])) {
                AuditoriaModel::create([
                    'accion' => 'CONSOLIDAR_SEMANA', 'tabla_afectada' => 'obligaciones_pago',
                    'registro_id' => $pendiente->id, 'datos_anteriores' => $pendiente->toArray(),
                    'datos_nuevos' => ['obligacion_destino_id' => $existentes[$clave]],
                    'motivo' => 'Fusión de pendientes del mismo proveedor y semana; se recalcula desde las entregas originales.',
                ]);
                $pendiente->delete();
            } else {
                $existentes[$clave] = $pendiente->id;
            }
        }

        ObligacionPagoDetalleModel::whereIn('obligacion_pago_id', $pendientes->pluck('id'))->delete();

        return $existentes;
    }

    public function semanasCerradas(int $proveedorId): array
    {
        return ObligacionPagoModel::where('tipo_beneficiario', TipoBeneficiarioPago::PROVEEDOR->value)
            ->where('proveedor_id', $proveedorId)
            ->where(fn (Builder $query) => $query->where('estado', EstadoObligacionPago::PAGADO->value)->orWhereNotNull('liquidacion_id'))
            ->get()->map(fn (ObligacionPagoModel $o): string => PeriodoPago::semanaQueContiene(DateTimeImmutable::createFromInterface($o->periodo_inicio))->clave())
            ->unique()->values()->all();
    }

    public function guardarPendiente(?int $existenteId, int $proveedorId, string $nombre, ?int $configuracionId, PeriodoPago $periodo, float $monto, array $baseCalculo, array $detalles, ?int $actorUserId): bool
    {
        $claveAbierta = 'PROVEEDOR:'.$proveedorId.':'.$periodo->clave();
        $datos = [
            'clave_abierta' => $claveAbierta,
            'periodo_inicio' => $periodo->inicio->format('Y-m-d'),
            'periodo_fin' => $periodo->fin->format('Y-m-d'),
            'fecha_pago_programada' => $periodo->fechaPagoProgramada()->format('Y-m-d'),
            'beneficiario_nombre' => $nombre,
            'configuracion_pago_id' => $configuracionId,
            'concepto' => 'Por litros entregados',
            'base_calculo' => $baseCalculo,
            'monto_calculado' => $monto,
        ];

        $obligacion = $existenteId !== null
            ? $this->pendientesPropios($proveedorId)->whereKey($existenteId)->lockForUpdate()->first()
            : null;
        $creada = $obligacion === null;

        if ($creada) {
            try {
                $obligacion = ObligacionPagoModel::create($datos + [
                    'tipo_beneficiario' => TipoBeneficiarioPago::PROVEEDOR->value,
                    'proveedor_id' => $proveedorId,
                    'periodo_inicio' => $periodo->inicio->format('Y-m-d'),
                    'periodo_fin' => $periodo->fin->format('Y-m-d'),
                    'periodicidad' => $periodo->periodicidad->value,
                    'estado' => EstadoObligacionPago::PENDIENTE->value,
                    'clave_unicidad' => $claveAbierta,
                    'clave_abierta' => $claveAbierta,
                    'creado_por_user_id' => $actorUserId,
                ]);
            } catch (UniqueConstraintViolationException) {
                $obligacion = ObligacionPagoModel::where('clave_abierta', $claveAbierta)->firstOrFail();
                $obligacion->update($datos);
                $creada = false;
            }
        } else {
            $obligacion->update($datos);
        }

        ObligacionPagoDetalleModel::where('obligacion_pago_id', $obligacion->id)->delete();

        foreach ($detalles as $detalle) {
            ObligacionPagoDetalleModel::create([
                'obligacion_pago_id' => $obligacion->id,
                'entrega_id' => $detalle->entregaId,
                'litros_entregados' => $detalle->litrosEntregados,
                'litros_pagables' => $detalle->litrosPagables,
                'resultado_calidad' => $detalle->resultado,
                'precio_base' => $detalle->precioBase,
                'ajuste_calidad' => $detalle->ajuste,
                'precio_final' => $detalle->precioFinal,
                'subtotal' => $detalle->subtotal,
            ]);
        }

        return $creada;
    }

    public function eliminarPendientes(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $obsoletas = ObligacionPagoModel::whereIn('id', $ids)
            ->where('estado', EstadoObligacionPago::PENDIENTE->value)
            ->whereNull('liquidacion_id')->get();
        foreach ($obsoletas as $obsoleta) {
            AuditoriaModel::create([
                'accion' => 'RECALCULAR_SEMANA', 'tabla_afectada' => 'obligaciones_pago', 'registro_id' => $obsoleta->id,
                'datos_anteriores' => $obsoleta->toArray(),
                'motivo' => 'Pendiente reemplazado por recálculo: sin saldo pagable o entregas trasladadas a otra semana disponible.',
            ]);
            $obsoleta->delete();
        }
    }

    public function descuentosDisponibles(int $proveedorId, PeriodoPago $periodo): array
    {
        $cerradas = array_fill_keys($this->semanasCerradas($proveedorId), true);

        $consumidos = AjusteLiquidacionModel::query()
            ->whereNotNull('venta_producto_id')
            ->whereHas('liquidacion', fn ($liquidacion) => $liquidacion->where('estado', '<>', 'ANULADA'))
            ->pluck('venta_producto_id')
            ->all();

        ObligacionPagoModel::where('proveedor_id', $proveedorId)
            ->where('estado', EstadoObligacionPago::PAGADO->value)
            ->whereNull('liquidacion_id')
            ->get()
            ->each(function (ObligacionPagoModel $pagada) use (&$consumidos): void {
                foreach ($pagada->base_calculo['descuentos'] ?? [] as $descuento) {
                    $consumidos[] = $descuento['venta_id'];
                }
            });

        return VentaProductoModel::query()
            ->where('proveedor_id', $proveedorId)
            ->whereDate('fecha', '<=', $periodo->fin->format('Y-m-d'))
            ->where('forma_pago', 'DESCUENTO_PLANILLA')
            ->where('estado', '<>', 'ANULADA')
            ->whereNotIn('id', $consumidos)
            ->get()
            ->filter(function (VentaProductoModel $venta) use ($periodo, $cerradas): bool {
                $destino = PeriodoPago::semanaQueContiene(DateTimeImmutable::createFromInterface($venta->fecha));
                while (isset($cerradas[$destino->clave()])) {
                    $destino = $destino->siguiente();
                }

                return $destino->clave() === $periodo->clave();
            })
            ->map(fn (VentaProductoModel $venta): array => ['venta_id' => $venta->id, 'importe' => (float) $venta->importe_total])
            ->values()->all();
    }

    private function pendientesPropios(int $proveedorId): Builder
    {
        return ObligacionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PROVEEDOR->value)
            ->where('proveedor_id', $proveedorId)
            ->where('estado', EstadoObligacionPago::PENDIENTE->value)
            ->whereNull('liquidacion_id');
    }
}
