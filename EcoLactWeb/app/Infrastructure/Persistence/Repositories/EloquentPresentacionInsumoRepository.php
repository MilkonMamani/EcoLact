<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\PresentacionInsumo;
use App\Domain\Produccion\PresentacionInsumoRepository;
use App\Infrastructure\Persistence\Models\PresentacionInsumoModel;
use DomainException;

class EloquentPresentacionInsumoRepository implements PresentacionInsumoRepository
{
    public function guardar(PresentacionInsumo $presentacion): PresentacionInsumo
    {
        return $this->toEntity(PresentacionInsumoModel::create($this->toModelData($presentacion)));
    }

    public function actualizar(PresentacionInsumo $presentacion): PresentacionInsumo
    {
        $model = PresentacionInsumoModel::find($presentacion->id())
            ?? throw new DomainException('La presentación indicada no existe.');

        $model->update($this->toModelData($presentacion));

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?PresentacionInsumo
    {
        $model = PresentacionInsumoModel::find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function listarPorInsumo(int $insumoId): array
    {
        return PresentacionInsumoModel::where('insumo_id', $insumoId)
            ->orderByRaw("estado = 'ACTIVA' DESC")
            ->orderBy('nombre')
            ->orderBy('cantidad_base')
            ->get()
            ->map(fn (PresentacionInsumoModel $model): PresentacionInsumo => $this->toEntity($model))
            ->all();
    }

    public function listarActivasAgrupadas(): array
    {
        $agrupadas = [];

        PresentacionInsumoModel::where('estado', PresentacionInsumo::ACTIVA)
            ->orderBy('nombre')
            ->orderBy('cantidad_base')
            ->get()
            ->each(function (PresentacionInsumoModel $model) use (&$agrupadas): void {
                $agrupadas[(int) $model->insumo_id][] = $this->toEntity($model);
            });

        return $agrupadas;
    }

    private function toEntity(PresentacionInsumoModel $model): PresentacionInsumo
    {
        return new PresentacionInsumo(
            id: $model->id,
            insumoId: (int) $model->insumo_id,
            nombre: $model->nombre,
            cantidad: (float) $model->cantidad,
            unidad: $model->unidad,
            cantidadBase: (float) $model->cantidad_base,
            estado: $model->estado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(PresentacionInsumo $presentacion): array
    {
        return [
            'insumo_id' => $presentacion->insumoId(),
            'nombre' => $presentacion->nombre(),
            'cantidad' => $presentacion->cantidad(),
            'unidad' => $presentacion->unidad(),
            'cantidad_base' => $presentacion->cantidadBase(),
            'estado' => $presentacion->estado(),
        ];
    }
}
