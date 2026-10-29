<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Compra\PagoCompra;
use App\Domain\Compra\PagoCompraRepository;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\PagoCompraModel;
use DateTimeImmutable;

class EloquentPagoCompraRepository implements PagoCompraRepository
{
    public function guardar(PagoCompra $pago): PagoCompra
    {
        return $this->toEntity(PagoCompraModel::create([
            'compra_insumo_id' => $pago->compraInsumoId(),
            'monto' => $pago->monto(),
            'metodo_pago' => $pago->metodoPago()?->value,
            'numero_operacion' => $pago->numeroOperacion(),
            'fecha' => $pago->fecha()->format('Y-m-d H:i:s'),
            'observacion' => $pago->observacion(),
            'movimiento_caja_id' => $pago->movimientoCajaId(),
            'registrado_por_user_id' => $pago->registradoPorUserId(),
        ]));
    }

    public function enlazarMovimientoCaja(int $pagoId, int $movimientoCajaId): void
    {
        PagoCompraModel::whereKey($pagoId)->update(['movimiento_caja_id' => $movimientoCajaId]);
    }

    public function listarPorCompra(int $compraId): array
    {
        return PagoCompraModel::where('compra_insumo_id', $compraId)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->map(fn (PagoCompraModel $model): PagoCompra => $this->toEntity($model))
            ->all();
    }

    public function totalPagado(int $compraId): float
    {
        return round((float) PagoCompraModel::where('compra_insumo_id', $compraId)->sum('monto'), 2);
    }

    private function toEntity(PagoCompraModel $model): PagoCompra
    {
        return new PagoCompra(
            id: $model->id,
            compraInsumoId: (int) $model->compra_insumo_id,
            monto: (float) $model->monto,
            metodoPago: MetodoPago::tryFrom((string) $model->metodo_pago),
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
            registradoPorUserId: (int) $model->registrado_por_user_id,
            numeroOperacion: $model->numero_operacion,
            observacion: $model->observacion,
            movimientoCajaId: $model->movimiento_caja_id !== null ? (int) $model->movimiento_caja_id : null,
        );
    }
}
