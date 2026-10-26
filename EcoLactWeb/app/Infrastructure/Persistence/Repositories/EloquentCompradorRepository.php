<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Ventas\Comprador;
use App\Domain\Ventas\CompradorRepository;
use App\Infrastructure\Persistence\Models\CompradorModel;

class EloquentCompradorRepository implements CompradorRepository
{
    public function guardar(Comprador $comprador): Comprador
    {
        $model = CompradorModel::create($this->toModelData($comprador));

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Comprador
    {
        $model = CompradorModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Comprador[]
     */
    public function listarActivos(): array
    {
        return CompradorModel::where('estado', 'ACTIVO')
            ->orderBy('nombre')
            ->get()
            ->map(fn (CompradorModel $model): Comprador => $this->toEntity($model))
            ->all();
    }

    private function toEntity(CompradorModel $model): Comprador
    {
        return new Comprador(
            id: $model->id,
            nombre: $model->nombre,
            documento: $model->documento,
            telefono: $model->telefono,
            estado: $model->estado,
            tipoCliente: $model->tipo_cliente,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Comprador $comprador): array
    {
        return [
            'nombre' => $comprador->nombre(),
            'documento' => $comprador->documento(),
            'telefono' => $comprador->telefono(),
            'tipo_cliente' => $comprador->tipoCliente(),
            'estado' => $comprador->estado(),
        ];
    }
}
