<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use DateTimeImmutable;

class EloquentAnalisisCalidadRepository implements AnalisisCalidadRepository
{
    public function guardar(AnalisisCalidad $analisis): AnalisisCalidad
    {
        $model = AnalisisCalidadModel::create($this->toModelData($analisis));

        return $this->toEntity($model);
    }

    public function actualizar(AnalisisCalidad $analisis): AnalisisCalidad
    {
        $model = AnalisisCalidadModel::findOrFail($analisis->id());
        $model->fill($this->toModelData($analisis));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?AnalisisCalidad
    {
        $model = AnalisisCalidadModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorEntregaId(int $entregaId): ?AnalisisCalidad
    {
        $model = AnalisisCalidadModel::where('entrega_id', $entregaId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return AnalisisCalidad[]
     */
    public function listar(): array
    {
        return AnalisisCalidadModel::orderByDesc('fecha_analisis')
            ->get()
            ->map(fn (AnalisisCalidadModel $model): AnalisisCalidad => $this->toEntity($model))
            ->all();
    }

    /**
     * @return AnalisisCalidad[]
     */
    public function listarPorProveedorId(int $proveedorId): array
    {
        return AnalisisCalidadModel::query()
            ->whereHas('entrega', fn ($query) => $query->where('proveedor_id', $proveedorId))
            ->orderByDesc('fecha_analisis')
            ->get()
            ->map(fn (AnalisisCalidadModel $model): AnalisisCalidad => $this->toEntity($model))
            ->all();
    }

    private function toEntity(AnalisisCalidadModel $model): AnalisisCalidad
    {
        return new AnalisisCalidad(
            id: $model->id,
            entregaId: $model->entrega_id,
            imagenPath: $model->imagen_path,
            estado: EstadoAnalisis::from($model->estado),
            resultado: $model->resultado !== null ? ResultadoCalidad::from($model->resultado) : null,
            fechaAnalisis: DateTimeImmutable::createFromInterface($model->fecha_analisis),
            confirmadoPorUserId: $model->confirmado_por_user_id,
            observacion: $model->observacion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(AnalisisCalidad $analisis): array
    {
        return [
            'entrega_id' => $analisis->entregaId(),
            'imagen_path' => $analisis->imagenPath(),
            'estado' => $analisis->estado()->value,
            'resultado' => $analisis->resultado()?->value,
            'fecha_analisis' => $analisis->fechaAnalisis()->format('Y-m-d H:i:s'),
            'confirmado_por_user_id' => $analisis->confirmadoPorUserId(),
            'observacion' => $analisis->observacion(),
        ];
    }
}
