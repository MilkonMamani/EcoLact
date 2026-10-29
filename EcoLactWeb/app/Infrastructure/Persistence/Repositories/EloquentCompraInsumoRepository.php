<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\CompraInsumo;
use App\Domain\Produccion\CompraInsumoDetalle;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Shared\Enums\EstadoRecepcionCompra;
use App\Domain\Shared\Enums\TipoComprobanteCompra;
use App\Domain\Shared\Enums\TratamientoCompra;
use App\Infrastructure\Persistence\Models\CompraInsumoDetalleModel;
use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use DateTimeImmutable;

class EloquentCompraInsumoRepository implements CompraInsumoRepository
{
    public function guardar(CompraInsumo $compra): CompraInsumo
    {
        $model = CompraInsumoModel::create([
            'origen' => $compra->origen(),
            'categoria_compra_id' => $compra->categoriaCompraId(),
            'proveedor_comercial_id' => $compra->proveedorComercialId(),
            'proveedor_comercial' => $compra->proveedorComercial(),
            'ruc' => $compra->ruc(),
            'tipo_comprobante' => $compra->tipoComprobante()?->value,
            'numero_comprobante' => $compra->numeroComprobante(),
            'fecha' => $compra->fecha()->format('Y-m-d'),
            'moneda' => $compra->moneda(),
            'concepto' => $compra->concepto(),
            'impuesto' => $compra->impuesto(),
            'total' => $compra->total(),
            'estado_recepcion' => $compra->estadoRecepcion()->value,
            'recibida_at' => $compra->recibidaEn()?->format('Y-m-d H:i:s'),
            'recibida_por_user_id' => $compra->recibidaEn() !== null ? $compra->registradoPorUserId() : null,
            'estado_pago' => $compra->estadoPago()->value,
            'monto_pagado' => $compra->montoPagado(),
            'imagen_boleta' => $compra->imagenBoleta(),
            'observacion' => $compra->observacion(),
            'registrado_por_user_id' => $compra->registradoPorUserId(),
        ]);

        // El código nace del id: "C-0001", igual que las compras históricas.
        $model->forceFill(['codigo' => CompraInsumo::codigoPara((int) $model->id)])->save();

        return $this->toEntity($model);
    }

    public function guardarDetalle(CompraInsumoDetalle $detalle): CompraInsumoDetalle
    {
        return $this->toDetalle(CompraInsumoDetalleModel::create([
            'compra_insumo_id' => $detalle->compraInsumoId(),
            'tratamiento' => $detalle->tratamiento()->value,
            'descripcion' => $detalle->descripcion(),
            'insumo_id' => $detalle->insumoId(),
            'presentacion_insumo_id' => $detalle->presentacionInsumoId(),
            'presentacion_nombre' => $detalle->presentacionNombre(),
            'presentacion_cantidad' => $detalle->presentacionCantidad(),
            'presentacion_unidad' => $detalle->presentacionUnidad(),
            'cantidad_presentaciones' => $detalle->cantidadPresentaciones(),
            'unidad' => $detalle->unidad(),
            'precio_unitario' => $detalle->precioUnitario(),
            'subtotal' => $detalle->subtotal(),
            'cantidad_base' => $detalle->cantidadBase(),
            'unidad_base' => $detalle->unidadBase(),
            'insumo_movimiento_id' => $detalle->insumoMovimientoId(),
        ]));
    }

    public function buscarPorId(int $id): ?CompraInsumo
    {
        $model = CompraInsumoModel::find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function buscarParaActualizar(int $id): ?CompraInsumo
    {
        $model = CompraInsumoModel::whereKey($id)->lockForUpdate()->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function listarDetalles(int $compraId): array
    {
        return CompraInsumoDetalleModel::where('compra_insumo_id', $compraId)
            ->orderBy('id')
            ->get()
            ->map(fn (CompraInsumoDetalleModel $model): CompraInsumoDetalle => $this->toDetalle($model))
            ->all();
    }

    public function buscarVarias(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return CompraInsumoModel::whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (CompraInsumoModel $model): array => [(int) $model->id => $this->toEntity($model)])
            ->all();
    }

    public function ultimaCompraPorInsumo(): array
    {
        return CompraInsumoDetalleModel::query()
            ->join('compras_insumo', 'compras_insumo.id', '=', 'compra_insumo_detalles.compra_insumo_id')
            ->whereNotNull('compra_insumo_detalles.insumo_id')
            ->groupBy('compra_insumo_detalles.insumo_id')
            ->selectRaw('compra_insumo_detalles.insumo_id as insumo_id, MAX(compras_insumo.fecha) as fecha')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->insumo_id => new DateTimeImmutable((string) $fila->fecha)])
            ->all();
    }

    public function enlazarMovimientoDetalle(int $detalleId, int $insumoMovimientoId): void
    {
        CompraInsumoDetalleModel::whereKey($detalleId)->update(['insumo_movimiento_id' => $insumoMovimientoId]);
    }

    public function actualizarRecepcion(int $compraId, EstadoRecepcionCompra $estado, ?DateTimeImmutable $recibidaEn, ?int $recibidaPorUserId): void
    {
        CompraInsumoModel::whereKey($compraId)->update([
            'estado_recepcion' => $estado->value,
            'recibida_at' => $recibidaEn?->format('Y-m-d H:i:s'),
            'recibida_por_user_id' => $recibidaPorUserId,
        ]);
    }

    public function actualizarPagos(CompraInsumo $compra, float $montoPagado): void
    {
        $actualizada = new CompraInsumo(
            id: $compra->id(),
            origen: $compra->origen(),
            fecha: $compra->fecha(),
            registradoPorUserId: $compra->registradoPorUserId(),
            total: $compra->total(),
            montoPagado: $montoPagado,
        );

        CompraInsumoModel::whereKey($compra->id())->update([
            'monto_pagado' => $actualizada->montoPagado(),
            'estado_pago' => $actualizada->estadoPago()->value,
        ]);
    }

    private function toEntity(CompraInsumoModel $model): CompraInsumo
    {
        return new CompraInsumo(
            id: $model->id,
            origen: $model->origen,
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
            registradoPorUserId: (int) $model->registrado_por_user_id,
            proveedorComercial: $model->proveedor_comercial,
            ruc: $model->ruc,
            numeroComprobante: $model->numero_comprobante,
            total: $model->total !== null ? (float) $model->total : null,
            imagenBoleta: $model->imagen_boleta,
            observacion: $model->observacion,
            registradoEn: $model->created_at !== null ? DateTimeImmutable::createFromInterface($model->created_at) : null,
            codigo: $model->codigo,
            categoriaCompraId: $model->categoria_compra_id !== null ? (int) $model->categoria_compra_id : null,
            proveedorComercialId: $model->proveedor_comercial_id !== null ? (int) $model->proveedor_comercial_id : null,
            tipoComprobante: TipoComprobanteCompra::tryFrom((string) $model->tipo_comprobante),
            moneda: $model->moneda ?? 'PEN',
            concepto: $model->concepto,
            impuesto: (float) ($model->impuesto ?? 0),
            estadoRecepcion: EstadoRecepcionCompra::tryFrom((string) $model->estado_recepcion) ?? EstadoRecepcionCompra::RECIBIDA,
            recibidaEn: $model->recibida_at !== null ? DateTimeImmutable::createFromInterface($model->recibida_at) : null,
            montoPagado: (float) ($model->monto_pagado ?? 0),
        );
    }

    private function toDetalle(CompraInsumoDetalleModel $model): CompraInsumoDetalle
    {
        return new CompraInsumoDetalle(
            id: $model->id,
            compraInsumoId: (int) $model->compra_insumo_id,
            insumoId: $model->insumo_id !== null ? (int) $model->insumo_id : null,
            presentacionInsumoId: $model->presentacion_insumo_id !== null ? (int) $model->presentacion_insumo_id : null,
            presentacionNombre: $model->presentacion_nombre,
            presentacionCantidad: $model->presentacion_cantidad !== null ? (float) $model->presentacion_cantidad : null,
            presentacionUnidad: $model->presentacion_unidad,
            cantidadPresentaciones: (float) $model->cantidad_presentaciones,
            cantidadBase: $model->cantidad_base !== null ? (float) $model->cantidad_base : null,
            unidadBase: $model->unidad_base,
            insumoMovimientoId: $model->insumo_movimiento_id !== null ? (int) $model->insumo_movimiento_id : null,
            precioUnitario: $model->precio_unitario !== null ? (float) $model->precio_unitario : null,
            tratamiento: TratamientoCompra::tryFrom((string) $model->tratamiento) ?? TratamientoCompra::INVENTARIO,
            descripcion: $model->descripcion,
            unidad: $model->unidad,
        );
    }
}
