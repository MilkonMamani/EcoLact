<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Recorrido\RecorridoAcopio;
use App\Domain\Recorrido\RecorridoAcopioRepository;
use App\Domain\Shared\Enums\EstadoRecorrido;
use App\Infrastructure\Persistence\Models\RecorridoAcopioModel;
use DateTimeImmutable;

class EloquentRecorridoAcopioRepository implements RecorridoAcopioRepository
{
    public function guardar(RecorridoAcopio $recorrido): RecorridoAcopio
    {
        $model = RecorridoAcopioModel::create($this->toModelData($recorrido));

        return $this->toEntity($model);
    }

    public function actualizar(RecorridoAcopio $recorrido): RecorridoAcopio
    {
        $model = RecorridoAcopioModel::findOrFail($recorrido->id());
        $model->fill($this->toModelData($recorrido));
        $model->save();

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?RecorridoAcopio
    {
        $model = RecorridoAcopioModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarAbiertoPorRutaYFecha(int $rutaId, DateTimeImmutable $fechaOperativa): ?RecorridoAcopio
    {
        $model = RecorridoAcopioModel::where('ruta_id', $rutaId)
            ->whereDate('fecha_operativa', $fechaOperativa->format('Y-m-d'))
            ->whereIn('estado', [EstadoRecorrido::PENDIENTE->value, EstadoRecorrido::EN_PROGRESO->value])
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return RecorridoAcopio[]
     */
    public function listarPorAcopiador(int $acopiadorId): array
    {
        return RecorridoAcopioModel::where('acopiador_id', $acopiadorId)
            ->orderByDesc('fecha_operativa')
            ->get()
            ->map(fn (RecorridoAcopioModel $model): RecorridoAcopio => $this->toEntity($model))
            ->all();
    }

    /**
     * @return RecorridoAcopio[]
     */
    public function listarPorRuta(int $rutaId): array
    {
        return RecorridoAcopioModel::where('ruta_id', $rutaId)
            ->orderByDesc('fecha_operativa')
            ->get()
            ->map(fn (RecorridoAcopioModel $model): RecorridoAcopio => $this->toEntity($model))
            ->all();
    }

    private function toEntity(RecorridoAcopioModel $model): RecorridoAcopio
    {
        return new RecorridoAcopio(
            id: $model->id,
            rutaId: $model->ruta_id,
            acopiadorId: $model->acopiador_id,
            fechaOperativa: DateTimeImmutable::createFromInterface($model->fecha_operativa),
            estado: EstadoRecorrido::from($model->estado),
            iniciadoEn: $model->iniciado_en ? DateTimeImmutable::createFromInterface($model->iniciado_en) : null,
            finalizadoEn: $model->finalizado_en ? DateTimeImmutable::createFromInterface($model->finalizado_en) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(RecorridoAcopio $recorrido): array
    {
        return [
            'ruta_id' => $recorrido->rutaId(),
            'acopiador_id' => $recorrido->acopiadorId(),
            'fecha_operativa' => $recorrido->fechaOperativa()->format('Y-m-d'),
            'estado' => $recorrido->estado()->value,
            'iniciado_en' => $recorrido->iniciadoEn()?->format('Y-m-d H:i:s'),
            'finalizado_en' => $recorrido->finalizadoEn()?->format('Y-m-d H:i:s'),
        ];
    }
}
