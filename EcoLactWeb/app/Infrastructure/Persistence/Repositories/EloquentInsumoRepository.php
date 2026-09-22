<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Shared\Enums\TipoInsumo;
use App\Infrastructure\Persistence\Models\InsumoModel;

class EloquentInsumoRepository implements InsumoRepository
{
    public function guardar(Insumo $insumo): Insumo
    {
        $model = InsumoModel::create($this->toModelData($insumo));

        return $this->toEntity($model);
    }

    public function actualizar(Insumo $insumo): Insumo
    {
        $model = InsumoModel::findOrFail($insumo->id());
        $model->fill($this->toModelData($insumo));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Insumo
    {
        $model = InsumoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function bloquearPorId(int $id): ?Insumo
    {
        $model = InsumoModel::query()->whereKey($id)->lockForUpdate()->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorNombre(string $nombre): ?Insumo
    {
        $model = InsumoModel::where('nombre', $nombre)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorCodigo(string $codigo): ?Insumo
    {
        $model = InsumoModel::where('codigo', $codigo)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Insumo[]
     */
    public function listarActivos(): array
    {
        return InsumoModel::where('estado', 'ACTIVO')
            ->orderByRaw("CASE WHEN codigo = 'LECHE' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get()
            ->map(fn (InsumoModel $model): Insumo => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Insumo[]
     */
    public function listarBajoMinimo(): array
    {
        return InsumoModel::where('estado', 'ACTIVO')
            ->where('stock_minimo', '>', 0)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orderByRaw("CASE WHEN codigo = 'LECHE' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get()
            ->map(fn (InsumoModel $model): Insumo => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Insumo[]
     */
    public function listar(): array
    {
        return InsumoModel::query()
            ->orderByRaw("CASE WHEN codigo = 'LECHE' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get()
            ->map(fn (InsumoModel $model): Insumo => $this->toEntity($model))
            ->all();
    }

    private function toEntity(InsumoModel $model): Insumo
    {
        return new Insumo(
            id: $model->id,
            nombre: $model->nombre,
            unidadBase: $model->unidad_base,
            stockActual: (float) $model->stock_actual,
            stockMinimo: (float) $model->stock_minimo,
            estado: $model->estado,
            tipo: TipoInsumo::tryFrom((string) $model->tipo) ?? TipoInsumo::INSUMO,
            codigo: $model->codigo,
            descripcion: $model->descripcion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Insumo $insumo): array
    {
        return [
            'codigo' => $insumo->codigo(),
            'nombre' => $insumo->nombre(),
            'tipo' => $insumo->tipo()->value,
            'descripcion' => $insumo->descripcion(),
            'unidad_base' => $insumo->unidadBase(),
            'stock_actual' => $insumo->stockActual(),
            'stock_minimo' => $insumo->stockMinimo(),
            'estado' => $insumo->estado(),
        ];
    }
}
