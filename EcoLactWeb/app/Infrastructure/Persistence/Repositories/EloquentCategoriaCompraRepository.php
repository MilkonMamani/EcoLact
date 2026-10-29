<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Compra\CategoriaCompra;
use App\Domain\Compra\CategoriaCompraRepository;
use App\Domain\Shared\Enums\CategoriaMovimientoCaja;
use App\Domain\Shared\Enums\TratamientoCompra;
use App\Infrastructure\Persistence\Models\CategoriaCompraModel;

class EloquentCategoriaCompraRepository implements CategoriaCompraRepository
{
    public function buscarPorId(int $id): ?CategoriaCompra
    {
        $model = CategoriaCompraModel::find($id);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function buscarPorCodigo(string $codigo): ?CategoriaCompra
    {
        $model = CategoriaCompraModel::where('codigo', $codigo)->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function listar(): array
    {
        return CategoriaCompraModel::orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (CategoriaCompraModel $model): CategoriaCompra => $this->toEntity($model))
            ->all();
    }

    private function toEntity(CategoriaCompraModel $model): CategoriaCompra
    {
        return new CategoriaCompra(
            id: (int) $model->id,
            codigo: $model->codigo,
            nombre: $model->nombre,
            descripcion: $model->descripcion,
            tratamientoSugerido: TratamientoCompra::tryFrom((string) $model->tratamiento_sugerido) ?? TratamientoCompra::CONSUMO,
            categoriaCaja: CategoriaMovimientoCaja::tryFrom((string) $model->categoria_caja) ?? CategoriaMovimientoCaja::GASTOS,
            activa: $model->estado === 'ACTIVA',
        );
    }
}
