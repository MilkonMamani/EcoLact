<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\CompraInsumo;
use App\Domain\Produccion\CompraInsumoDetalle;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Infrastructure\Persistence\Models\CompraInsumoDetalleModel;
use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use DateTimeImmutable;

class EloquentCompraInsumoRepository implements CompraInsumoRepository
{
    public function guardar(CompraInsumo $compra): CompraInsumo
    {
        return $this->toEntity(CompraInsumoModel::create([
            'origen' => $compra->origen(),
            'proveedor_comercial' => $compra->proveedorComercial(),
            'ruc' => $compra->ruc(),
            'numero_comprobante' => $compra->numeroComprobante(),
            'fecha' => $compra->fecha()->format('Y-m-d'),
            'total' => $compra->total(),
            'imagen_boleta' => $compra->imagenBoleta(),
            'observacion' => $compra->observacion(),
            'registrado_por_user_id' => $compra->registradoPorUserId(),
        ]));
    }

    public function guardarDetalle(CompraInsumoDetalle $detalle): CompraInsumoDetalle
    {
        return $this->toDetalle(CompraInsumoDetalleModel::create([
            'compra_insumo_id' => $detalle->compraInsumoId(),
            'insumo_id' => $detalle->insumoId(),
            'presentacion_insumo_id' => $detalle->presentacionInsumoId(),
            'presentacion_nombre' => $detalle->presentacionNombre(),
            'presentacion_cantidad' => $detalle->presentacionCantidad(),
            'presentacion_unidad' => $detalle->presentacionUnidad(),
            'cantidad_presentaciones' => $detalle->cantidadPresentaciones(),
            'precio_unitario' => $detalle->precioUnitario(),
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
            ->groupBy('compra_insumo_detalles.insumo_id')
            ->selectRaw('compra_insumo_detalles.insumo_id as insumo_id, MAX(compras_insumo.fecha) as fecha')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->insumo_id => new DateTimeImmutable((string) $fila->fecha)])
            ->all();
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
        );
    }

    private function toDetalle(CompraInsumoDetalleModel $model): CompraInsumoDetalle
    {
        return new CompraInsumoDetalle(
            id: $model->id,
            compraInsumoId: (int) $model->compra_insumo_id,
            insumoId: (int) $model->insumo_id,
            presentacionInsumoId: $model->presentacion_insumo_id !== null ? (int) $model->presentacion_insumo_id : null,
            presentacionNombre: $model->presentacion_nombre,
            presentacionCantidad: (float) $model->presentacion_cantidad,
            presentacionUnidad: $model->presentacion_unidad,
            cantidadPresentaciones: (float) $model->cantidad_presentaciones,
            cantidadBase: (float) $model->cantidad_base,
            unidadBase: $model->unidad_base,
            insumoMovimientoId: $model->insumo_movimiento_id !== null ? (int) $model->insumo_movimiento_id : null,
            precioUnitario: $model->precio_unitario !== null ? (float) $model->precio_unitario : null,
        );
    }
}
