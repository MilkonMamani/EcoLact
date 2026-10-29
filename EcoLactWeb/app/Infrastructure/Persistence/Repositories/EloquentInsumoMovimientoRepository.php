<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use DateTimeImmutable;

class EloquentInsumoMovimientoRepository implements InsumoMovimientoRepository
{
    public function guardar(InsumoMovimiento $movimiento): InsumoMovimiento
    {
        $model = InsumoMovimientoModel::create($this->toModelData($movimiento));

        return $this->toEntity($model);
    }

    /**
     * @return InsumoMovimiento[]
     */
    public function ultimoMovimientoPorInsumo(): array
    {
        return InsumoMovimientoModel::query()
            ->groupBy('insumo_id')
            ->selectRaw('insumo_id, MAX(fecha) as fecha')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->insumo_id => new DateTimeImmutable((string) $fila->fecha)])
            ->all();
    }

    public function listarPorInsumo(int $insumoId): array
    {
        return InsumoMovimientoModel::where('insumo_id', $insumoId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (InsumoMovimientoModel $model): InsumoMovimiento => $this->toEntity($model))
            ->all();
    }

    /**
     * @return InsumoMovimiento[]
     */
    public function listarPorOrigen(string $origenTipo, int $origenId): array
    {
        return InsumoMovimientoModel::where('origen_tipo', $origenTipo)
            ->where('origen_id', $origenId)
            ->orderBy('id')
            ->get()
            ->map(fn (InsumoMovimientoModel $model): InsumoMovimiento => $this->toEntity($model))
            ->all();
    }

    /**
     * @return InsumoMovimiento[]
     */
    public function listar(): array
    {
        return InsumoMovimientoModel::orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (InsumoMovimientoModel $model): InsumoMovimiento => $this->toEntity($model))
            ->all();
    }

    private function toEntity(InsumoMovimientoModel $model): InsumoMovimiento
    {
        return new InsumoMovimiento(
            id: $model->id,
            insumoId: $model->insumo_id,
            tipo: $model->tipo,
            motivo: $model->motivo,
            cantidad: (float) $model->cantidad,
            loteProduccionId: $model->lote_produccion_id,
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
            registradoPorUserId: $model->registrado_por_user_id,
            observacion: $model->observacion,
            origenTipo: $model->origen_tipo,
            origenId: $model->origen_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(InsumoMovimiento $movimiento): array
    {
        return [
            'insumo_id' => $movimiento->insumoId(),
            'tipo' => $movimiento->tipo(),
            'motivo' => $movimiento->motivo(),
            'cantidad' => $movimiento->cantidad(),
            'lote_produccion_id' => $movimiento->loteProduccionId(),
            'origen_tipo' => $movimiento->origenTipo(),
            'origen_id' => $movimiento->origenId(),
            'fecha' => $movimiento->fecha()->format('Y-m-d H:i:s'),
            'observacion' => $movimiento->observacion(),
            'registrado_por_user_id' => $movimiento->registradoPorUserId(),
        ];
    }
}
