<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Pago\Contracts\PagosPendientesProveedorStore;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\AjusteLiquidacionModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoDetalleModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentPagosPendientesProveedorStore implements PagosPendientesProveedorStore
{
    public function liberarPendientes(int $proveedorId): array
    {
        $pendientes = $this->pendientesPropios($proveedorId)->lockForUpdate()->get();

        ObligacionPagoDetalleModel::whereIn('obligacion_pago_id', $pendientes->pluck('id'))->delete();

        return $pendientes
            ->mapWithKeys(fn (ObligacionPagoModel $obligacion): array => [$obligacion->periodo_inicio->format('Y-m-d') => $obligacion->id])
            ->all();
    }

    public function guardarPendiente(?int $existenteId, int $proveedorId, string $nombre, ?int $configuracionId, PeriodoPago $periodo, float $monto, array $baseCalculo, array $detalles, ?int $actorUserId): bool
    {
        $claveAbierta = 'PROVEEDOR:'.$proveedorId.':'.$periodo->clave();
        $datos = [
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
                    'clave_unicidad' => $claveAbierta.':'.($this->contarDelPeriodo($proveedorId, $periodo) + 1),
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

        ObligacionPagoModel::whereIn('id', $ids)
            ->where('estado', EstadoObligacionPago::PENDIENTE->value)
            ->whereNull('liquidacion_id')
            ->delete();
    }

    public function descuentosDisponibles(int $proveedorId, PeriodoPago $periodo): array
    {
        $periodoModel = PeriodoLiquidacionModel::whereDate('fecha_inicio', $periodo->inicio->format('Y-m-d'))
            ->whereDate('fecha_fin', $periodo->fin->format('Y-m-d'))
            ->first();

        if ($periodoModel === null) {
            return [];
        }

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
            ->where('periodo_id', $periodoModel->id)
            ->where('forma_pago', 'DESCUENTO_PLANILLA')
            ->where('estado', '<>', 'ANULADA')
            ->whereNotIn('id', $consumidos)
            ->get()
            ->map(fn (VentaProductoModel $venta): array => ['venta_id' => $venta->id, 'importe' => (float) $venta->importe_total])
            ->all();
    }

    private function pendientesPropios(int $proveedorId): Builder
    {
        return ObligacionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PROVEEDOR->value)
            ->where('proveedor_id', $proveedorId)
            ->where('estado', EstadoObligacionPago::PENDIENTE->value)
            ->whereNull('liquidacion_id');
    }

    private function contarDelPeriodo(int $proveedorId, PeriodoPago $periodo): int
    {
        return ObligacionPagoModel::where('tipo_beneficiario', TipoBeneficiarioPago::PROVEEDOR->value)
            ->where('proveedor_id', $proveedorId)
            ->whereDate('periodo_inicio', $periodo->inicio->format('Y-m-d'))
            ->count();
    }
}
