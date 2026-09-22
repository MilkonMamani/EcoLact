<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\PagoProveedor;
use App\Domain\Pago\PagoProveedorRepository;
use App\Domain\Shared\Enums\EstadoPago;
use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use DateTimeImmutable;

class EloquentPagoProveedorRepository implements PagoProveedorRepository
{
    public function guardar(PagoProveedor $pago): PagoProveedor
    {
        $model = PagoProveedorModel::create($this->toModelData($pago));

        return $this->toEntity($model);
    }

    public function actualizar(PagoProveedor $pago): PagoProveedor
    {
        $model = PagoProveedorModel::findOrFail($pago->id());
        $model->fill($this->toModelData($pago));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?PagoProveedor
    {
        $model = PagoProveedorModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return PagoProveedor[]
     */
    public function listarPorLiquidacion(int $liquidacionId): array
    {
        return PagoProveedorModel::where('liquidacion_id', $liquidacionId)
            ->get()
            ->map(fn (PagoProveedorModel $model): PagoProveedor => $this->toEntity($model))
            ->all();
    }

    /**
     * @return PagoProveedor[]
     */
    public function listarPorProveedor(int $proveedorId): array
    {
        return PagoProveedorModel::whereHas(
            'liquidacion',
            fn ($query) => $query->where('proveedor_id', $proveedorId)
        )
            ->get()
            ->map(fn (PagoProveedorModel $model): PagoProveedor => $this->toEntity($model))
            ->all();
    }

    /**
     * @return PagoProveedor[]
     */
    public function listar(): array
    {
        return PagoProveedorModel::orderByDesc('fecha_pago')
            ->get()
            ->map(fn (PagoProveedorModel $model): PagoProveedor => $this->toEntity($model))
            ->all();
    }

    private function toEntity(PagoProveedorModel $model): PagoProveedor
    {
        return new PagoProveedor(
            id: $model->id,
            liquidacionId: $model->liquidacion_id,
            fechaPago: DateTimeImmutable::createFromInterface($model->fecha_pago),
            monto: (float) $model->monto,
            estado: EstadoPago::from($model->estado),
            registradoPorUserId: $model->registrado_por_user_id,
            observacion: $model->observacion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(PagoProveedor $pago): array
    {
        return [
            'liquidacion_id' => $pago->liquidacionId(),
            'fecha_pago' => $pago->fechaPago()->format('Y-m-d H:i:s'),
            'monto' => $pago->monto(),
            'estado' => $pago->estado()->value,
            'registrado_por_user_id' => $pago->registradoPorUserId(),
            'observacion' => $pago->observacion(),
        ];
    }
}
