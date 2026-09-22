<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\PagoRealizado;
use App\Domain\Pago\PagoRealizadoRepository;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\PagoModel;
use DateTimeImmutable;

class EloquentPagoRealizadoRepository implements PagoRealizadoRepository
{
    public function guardar(PagoRealizado $pago): PagoRealizado
    {
        $model = PagoModel::create([
            'obligacion_pago_id' => $pago->obligacionPagoId(),
            'fecha_pago' => $pago->fechaPago()->format('Y-m-d'),
            'pagado_at' => ($pago->pagadoAt() ?? now())->format('Y-m-d H:i:s'),
            'impreso_at' => $pago->impresoAt()?->format('Y-m-d H:i:s'),
            'lote_codigo' => $pago->loteCodigo(),
            'comprobante_snapshot' => $pago->comprobanteSnapshot(),
            'monto' => $pago->monto(),
            'metodo_pago' => $pago->metodoPago()->value,
            'numero_operacion' => $pago->numeroOperacion(),
            'observacion' => $pago->observacion(),
            'comprobante_path' => $pago->comprobantePath(),
            'comprobante_nombre' => $pago->comprobanteNombre(),
            'pago_proveedor_id' => $pago->pagoProveedorId(),
            'registrado_por_user_id' => $pago->registradoPorUserId(),
        ]);

        return $this->toEntity($model);
    }

    public function buscarPorObligacionId(int $obligacionId): ?PagoRealizado
    {
        $model = PagoModel::where('obligacion_pago_id', $obligacionId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    private function toEntity(PagoModel $model): PagoRealizado
    {
        return new PagoRealizado(
            id: $model->id,
            obligacionPagoId: $model->obligacion_pago_id,
            fechaPago: DateTimeImmutable::createFromInterface($model->fecha_pago),
            monto: (float) $model->monto,
            metodoPago: MetodoPago::from($model->metodo_pago),
            numeroOperacion: $model->numero_operacion,
            observacion: $model->observacion,
            comprobantePath: $model->comprobante_path,
            comprobanteNombre: $model->comprobante_nombre,
            registradoPorUserId: $model->registrado_por_user_id,
            pagoProveedorId: $model->pago_proveedor_id,
            pagadoAt: $model->pagado_at !== null ? DateTimeImmutable::createFromInterface($model->pagado_at) : null,
            impresoAt: $model->impreso_at !== null ? DateTimeImmutable::createFromInterface($model->impreso_at) : null,
            loteCodigo: $model->lote_codigo,
            comprobanteSnapshot: $model->comprobante_snapshot,
        );
    }
}
