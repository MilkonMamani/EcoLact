<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Ventas\VentaProducto;
use App\Domain\Ventas\VentaProductoRepository;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use DateTimeImmutable;

class EloquentVentaProductoRepository implements VentaProductoRepository
{
    public function guardar(VentaProducto $venta): VentaProducto
    {
        $model = VentaProductoModel::create($this->toModelData($venta));

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?VentaProducto
    {
        $model = VentaProductoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return VentaProducto[]
     */
    public function listarPorProveedorYPeriodo(int $proveedorId, int $periodoId, ?string $formaPago = null): array
    {
        $query = VentaProductoModel::where('proveedor_id', $proveedorId)
            ->where('periodo_id', $periodoId)
            ->where('estado', '!=', 'ANULADA');

        if ($formaPago !== null) {
            $query->where('forma_pago', $formaPago);
        }

        return $query->get()
            ->map(fn (VentaProductoModel $model): VentaProducto => $this->toEntity($model))
            ->all();
    }

    /**
     * @return VentaProducto[]
     */
    public function listarPorProveedor(int $proveedorId): array
    {
        return VentaProductoModel::where('proveedor_id', $proveedorId)
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (VentaProductoModel $model): VentaProducto => $this->toEntity($model))
            ->all();
    }

    /**
     * @return VentaProducto[]
     */
    public function listar(): array
    {
        return VentaProductoModel::orderByDesc('fecha')
            ->get()
            ->map(fn (VentaProductoModel $model): VentaProducto => $this->toEntity($model))
            ->all();
    }

    private function toEntity(VentaProductoModel $model): VentaProducto
    {
        return new VentaProducto(
            id: $model->id,
            productoId: $model->producto_id,
            proveedorId: $model->proveedor_id,
            compradorId: $model->comprador_id,
            tipoCliente: $model->tipo_cliente,
            cantidad: (float) $model->cantidad,
            precioUnitario: (float) $model->precio_unitario,
            importeTotal: (float) $model->importe_total,
            formaPago: $model->forma_pago,
            periodoId: $model->periodo_id,
            estado: $model->estado,
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
            registradoPorUserId: $model->registrado_por_user_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(VentaProducto $venta): array
    {
        return [
            'producto_id' => $venta->productoId(),
            'proveedor_id' => $venta->proveedorId(),
            'comprador_id' => $venta->compradorId(),
            'tipo_cliente' => $venta->tipoCliente(),
            'cantidad' => $venta->cantidad(),
            'precio_unitario' => $venta->precioUnitario(),
            'importe_total' => $venta->importeTotal(),
            'forma_pago' => $venta->formaPago(),
            'periodo_id' => $venta->periodoId(),
            'estado' => $venta->estado(),
            'fecha' => $venta->fecha()->format('Y-m-d H:i:s'),
            'registrado_por_user_id' => $venta->registradoPorUserId(),
        ];
    }
}
