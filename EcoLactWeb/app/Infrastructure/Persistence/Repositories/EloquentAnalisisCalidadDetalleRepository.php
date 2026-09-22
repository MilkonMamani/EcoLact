<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Calidad\AnalisisCalidadDetalle;
use App\Domain\Calidad\AnalisisCalidadDetalleRepository;
use App\Infrastructure\Persistence\Models\AnalisisCalidadDetalleModel;

class EloquentAnalisisCalidadDetalleRepository implements AnalisisCalidadDetalleRepository
{
    public function guardar(AnalisisCalidadDetalle $detalle): AnalisisCalidadDetalle
    {
        $model = AnalisisCalidadDetalleModel::updateOrCreate(
            [
                'analisis_id' => $detalle->analisisId(),
                'parametro_id' => $detalle->parametroId(),
            ],
            $this->toModelData($detalle),
        );

        return $this->toEntity($model);
    }

    /**
     * @return AnalisisCalidadDetalle[]
     */
    public function listarPorAnalisis(int $analisisId): array
    {
        return AnalisisCalidadDetalleModel::where('analisis_id', $analisisId)
            ->get()
            ->map(fn (AnalisisCalidadDetalleModel $model): AnalisisCalidadDetalle => $this->toEntity($model))
            ->all();
    }

    private function toEntity(AnalisisCalidadDetalleModel $model): AnalisisCalidadDetalle
    {
        return new AnalisisCalidadDetalle(
            id: $model->id,
            analisisId: $model->analisis_id,
            parametroId: $model->parametro_id,
            valor: (float) $model->valor,
            origen: $model->origen,
            confianzaOcr: $model->confianza_ocr !== null ? (float) $model->confianza_ocr : null,
            cumple: $model->cumple !== null ? (bool) $model->cumple : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(AnalisisCalidadDetalle $detalle): array
    {
        return [
            'analisis_id' => $detalle->analisisId(),
            'parametro_id' => $detalle->parametroId(),
            'valor' => $detalle->valor(),
            'origen' => $detalle->origen(),
            'confianza_ocr' => $detalle->confianzaOcr(),
            'cumple' => $detalle->cumple(),
        ];
    }
}
