<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\PrecioLeche;
use App\Domain\Pago\PrecioLecheRepository;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use DateTimeImmutable;

class EloquentPrecioLecheRepository implements PrecioLecheRepository
{
    public function guardar(PrecioLeche $precioLeche): PrecioLeche
    {
        $model = PrecioLecheModel::create($this->toModelData($precioLeche));

        return $this->toEntity($model);
    }

    public function actualizar(PrecioLeche $precioLeche): PrecioLeche
    {
        $model = PrecioLecheModel::findOrFail($precioLeche->id());
        $model->fill($this->toModelData($precioLeche));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?PrecioLeche
    {
        $model = PrecioLecheModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarVigente(DateTimeImmutable $fecha): ?PrecioLeche
    {
        $fechaSql = $fecha->format('Y-m-d');

        $model = PrecioLecheModel::where('estado', 'ACTIVO')
            ->whereDate('fecha_inicio', '<=', $fechaSql)
            ->where(function ($query) use ($fechaSql) {
                $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fechaSql);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return PrecioLeche[]
     */
    public function listar(): array
    {
        return PrecioLecheModel::orderByDesc('fecha_inicio')
            ->get()
            ->map(fn (PrecioLecheModel $model): PrecioLeche => $this->toEntity($model))
            ->all();
    }

    private function toEntity(PrecioLecheModel $model): PrecioLeche
    {
        return new PrecioLeche(
            id: $model->id,
            precioLitro: (float) $model->precio_litro,
            fechaInicio: DateTimeImmutable::createFromInterface($model->fecha_inicio),
            fechaFin: $model->fecha_fin ? DateTimeImmutable::createFromInterface($model->fecha_fin) : null,
            estado: $model->estado,
            creadoPorUserId: $model->creado_por_user_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(PrecioLeche $precioLeche): array
    {
        return [
            'precio_litro' => $precioLeche->precioLitro(),
            'fecha_inicio' => $precioLeche->fechaInicio()->format('Y-m-d'),
            'fecha_fin' => $precioLeche->fechaFin()?->format('Y-m-d'),
            'estado' => $precioLeche->estado(),
            'creado_por_user_id' => $precioLeche->creadoPorUserId(),
        ];
    }
}
