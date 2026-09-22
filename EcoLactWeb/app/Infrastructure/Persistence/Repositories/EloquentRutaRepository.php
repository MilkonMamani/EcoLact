<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Ruta\Ruta;
use App\Domain\Ruta\RutaRepository;
use App\Infrastructure\Persistence\Models\RutaModel;

class EloquentRutaRepository implements RutaRepository
{
    public function guardar(Ruta $ruta): Ruta
    {
        $model = RutaModel::create($this->toModelData($ruta));

        return $this->toEntity($model);
    }

    public function actualizar(Ruta $ruta): Ruta
    {
        $model = RutaModel::findOrFail($ruta->id());
        $model->fill($this->toModelData($ruta));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Ruta
    {
        $model = RutaModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorZonaYNombre(int $zonaId, string $nombre): ?Ruta
    {
        $model = RutaModel::where('zona_id', $zonaId)->where('nombre', $nombre)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Ruta[]
     */
    public function listarPorZona(int $zonaId): array
    {
        return RutaModel::where('zona_id', $zonaId)
            ->orderBy('nombre')
            ->get()
            ->map(fn (RutaModel $model): Ruta => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Ruta[]
     */
    public function listarActivasPorZona(int $zonaId): array
    {
        return RutaModel::where('zona_id', $zonaId)
            ->where('estado', 'ACTIVO')
            ->orderBy('nombre')
            ->get()
            ->map(fn (RutaModel $model): Ruta => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Ruta[]
     */
    public function listarActivas(): array
    {
        return RutaModel::where('estado', 'ACTIVO')
            ->orderBy('nombre')
            ->get()
            ->map(fn (RutaModel $model): Ruta => $this->toEntity($model))
            ->all();
    }

    private function toEntity(RutaModel $model): Ruta
    {
        return new Ruta(
            id: $model->id,
            zonaId: $model->zona_id,
            nombre: $model->nombre,
            descripcion: $model->descripcion,
            estado: $model->estado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Ruta $ruta): array
    {
        return [
            'zona_id' => $ruta->zonaId(),
            'nombre' => $ruta->nombre(),
            'descripcion' => $ruta->descripcion(),
            'estado' => $ruta->estado(),
        ];
    }
}
