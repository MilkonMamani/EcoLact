<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Shared\Enums\EstadoLiquidacion;
use App\Infrastructure\Persistence\Models\LiquidacionDetalleModel;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use DateTimeImmutable;

class EloquentLiquidacionRepository implements LiquidacionRepository
{
    public function guardar(Liquidacion $liquidacion): Liquidacion
    {
        $model = LiquidacionModel::create($this->toModelData($liquidacion));

        return $this->toEntity($model);
    }

    public function actualizar(Liquidacion $liquidacion): Liquidacion
    {
        $model = LiquidacionModel::findOrFail($liquidacion->id());
        $model->fill($this->toModelData($liquidacion));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Liquidacion
    {
        $model = LiquidacionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Liquidacion[]
     */
    public function listarPorProveedor(int $proveedorId): array
    {
        return LiquidacionModel::where('proveedor_id', $proveedorId)
            ->get()
            ->map(fn (LiquidacionModel $model): Liquidacion => $this->toEntity($model))
            ->all();
    }

    public function listarValorizacionIdsIncluidas(array $valorizacionIds): array
    {
        return LiquidacionDetalleModel::whereIn('valorizacion_entrega_id', $valorizacionIds)
            ->pluck('valorizacion_entrega_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return Liquidacion[]
     */
    public function listarPorPeriodo(int $periodoId): array
    {
        return LiquidacionModel::where('periodo_id', $periodoId)
            ->get()
            ->map(fn (LiquidacionModel $model): Liquidacion => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Liquidacion[]
     */
    public function listar(): array
    {
        return LiquidacionModel::orderByDesc('fecha_calculo')
            ->get()
            ->map(fn (LiquidacionModel $model): Liquidacion => $this->toEntity($model))
            ->all();
    }

    private function toEntity(LiquidacionModel $model): Liquidacion
    {
        return new Liquidacion(
            id: $model->id,
            periodoId: $model->periodo_id,
            proveedorId: $model->proveedor_id,
            totalLitrosEntregados: (float) $model->total_litros_entregados,
            totalLitrosPagables: (float) $model->total_litros_pagables,
            subtotal: (float) $model->subtotal,
            totalAjustes: (float) $model->total_ajustes,
            totalPagar: (float) $model->total_pagar,
            estado: EstadoLiquidacion::from($model->estado),
            fechaCalculo: DateTimeImmutable::createFromInterface($model->fecha_calculo),
            generadoPorUserId: $model->generado_por_user_id,
            precioLitroAplicado: (float) $model->precio_litro_aplicado,
            tuvoIncidencia: $model->tuvo_incidencia,
            sancionId: $model->sancion_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Liquidacion $liquidacion): array
    {
        return [
            'periodo_id' => $liquidacion->periodoId(),
            'proveedor_id' => $liquidacion->proveedorId(),
            'total_litros_entregados' => $liquidacion->totalLitrosEntregados(),
            'total_litros_pagables' => $liquidacion->totalLitrosPagables(),
            'subtotal' => $liquidacion->subtotal(),
            'total_ajustes' => $liquidacion->totalAjustes(),
            'total_pagar' => $liquidacion->totalPagar(),
            'estado' => $liquidacion->estado()->value,
            'fecha_calculo' => $liquidacion->fechaCalculo()->format('Y-m-d H:i:s'),
            'generado_por_user_id' => $liquidacion->generadoPorUserId(),
            'precio_litro_aplicado' => $liquidacion->precioLitroAplicado(),
            'tuvo_incidencia' => $liquidacion->tuvoIncidencia(),
            'sancion_id' => $liquidacion->sancionId(),
        ];
    }
}
