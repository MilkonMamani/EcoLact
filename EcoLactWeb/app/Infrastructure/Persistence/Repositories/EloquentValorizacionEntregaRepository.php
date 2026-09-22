<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\ValorizacionEntrega;
use App\Domain\Pago\ValorizacionEntregaRepository;
use App\Infrastructure\Persistence\Models\ValorizacionEntregaModel;
use DateTimeImmutable;

class EloquentValorizacionEntregaRepository implements ValorizacionEntregaRepository
{
    public function guardar(ValorizacionEntrega $valorizacion): ValorizacionEntrega
    {
        $model = ValorizacionEntregaModel::create($this->toModelData($valorizacion));

        return $this->toEntity($model);
    }

    public function buscarPorEntregaId(int $entregaId): ?ValorizacionEntrega
    {
        $model = ValorizacionEntregaModel::where('entrega_id', $entregaId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @param  int[]  $entregaIds
     * @return ValorizacionEntrega[]
     */
    public function listarPorEntregaIds(array $entregaIds): array
    {
        if ($entregaIds === []) {
            return [];
        }

        return ValorizacionEntregaModel::whereIn('entrega_id', $entregaIds)
            ->get()
            ->map(fn (ValorizacionEntregaModel $model): ValorizacionEntrega => $this->toEntity($model))
            ->all();
    }

    private function toEntity(ValorizacionEntregaModel $model): ValorizacionEntrega
    {
        return new ValorizacionEntrega(
            id: $model->id,
            entregaId: $model->entrega_id,
            analisisId: $model->analisis_id,
            precioLecheId: $model->precio_leche_id,
            reglaPagoId: $model->regla_pago_id,
            litrosEntregados: (float) $model->litros_entregados,
            litrosPagables: (float) $model->litros_pagables,
            precioBaseLitro: (float) $model->precio_base_litro,
            ajustePorLitro: (float) $model->ajuste_por_litro,
            precioFinalLitro: (float) $model->precio_final_litro,
            importeTotal: (float) $model->importe_total,
            fechaCalculo: DateTimeImmutable::createFromInterface($model->fecha_calculo),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(ValorizacionEntrega $valorizacion): array
    {
        return [
            'entrega_id' => $valorizacion->entregaId(),
            'analisis_id' => $valorizacion->analisisId(),
            'precio_leche_id' => $valorizacion->precioLecheId(),
            'regla_pago_id' => $valorizacion->reglaPagoId(),
            'litros_entregados' => $valorizacion->litrosEntregados(),
            'litros_pagables' => $valorizacion->litrosPagables(),
            'precio_base_litro' => $valorizacion->precioBaseLitro(),
            'ajuste_por_litro' => $valorizacion->ajustePorLitro(),
            'precio_final_litro' => $valorizacion->precioFinalLitro(),
            'importe_total' => $valorizacion->importeTotal(),
            'fecha_calculo' => $valorizacion->fechaCalculo()->format('Y-m-d H:i:s'),
        ];
    }
}
