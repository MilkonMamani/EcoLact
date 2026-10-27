<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Calidad\MuestraYaSeleccionadaException;
use App\Domain\Calidad\MuestreoCalidad;
use App\Domain\Calidad\MuestreoCalidadRepository;
use App\Domain\Shared\Enums\EstadoMuestreo;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Domain\Shared\Enums\TipoControlMuestreo;
use App\Infrastructure\Persistence\Models\MuestreoCalidadModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;

class EloquentMuestreoCalidadRepository implements MuestreoCalidadRepository
{
    public function guardar(MuestreoCalidad $muestreo): MuestreoCalidad
    {
        try {
            $model = MuestreoCalidadModel::create($this->toModelData($muestreo));
        } catch (UniqueConstraintViolationException) {
            // Dos analistas eligieron la misma entrega a la vez: gana el primero.
            throw new MuestraYaSeleccionadaException;
        }

        return $this->toEntity($model);
    }

    public function actualizar(MuestreoCalidad $muestreo): MuestreoCalidad
    {
        $model = MuestreoCalidadModel::find($muestreo->id())
            ?? throw new DomainException('La muestra de calidad indicada no existe.');

        $model->update($this->toModelData($muestreo));

        return $this->toEntity($model->refresh());
    }

    public function buscarPorId(int $id): ?MuestreoCalidad
    {
        $model = MuestreoCalidadModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorEntregaId(int $entregaId): ?MuestreoCalidad
    {
        $model = MuestreoCalidadModel::where('entrega_id', $entregaId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    private function toEntity(MuestreoCalidadModel $model): MuestreoCalidad
    {
        return new MuestreoCalidad(
            id: $model->id,
            entregaId: $model->entrega_id,
            seleccionadoPorUserId: $model->seleccionado_por_user_id,
            metodoSeleccion: MetodoSeleccionMuestreo::from($model->metodo_seleccion),
            tipoControl: $model->tipo_control !== null ? TipoControlMuestreo::from($model->tipo_control) : null,
            fechaSeleccion: DateTimeImmutable::createFromInterface($model->fecha_seleccion),
            estado: EstadoMuestreo::from($model->estado),
            observacion: $model->observacion,
            fechaCapturaLocal: $model->fecha_captura_local !== null ? DateTimeImmutable::createFromInterface($model->fecha_captura_local) : null,
        );
    }

    /** @return array<string, mixed> */
    private function toModelData(MuestreoCalidad $muestreo): array
    {
        return [
            'entrega_id' => $muestreo->entregaId(),
            'seleccionado_por_user_id' => $muestreo->seleccionadoPorUserId(),
            'metodo_seleccion' => $muestreo->metodoSeleccion()->value,
            'tipo_control' => $muestreo->tipoControl()?->value,
            'fecha_seleccion' => $muestreo->fechaSeleccion()->format('Y-m-d H:i:s'),
            'fecha_captura_local' => $muestreo->fechaCapturaLocal()?->format('Y-m-d H:i:s'),
            'estado' => $muestreo->estado()->value,
            'observacion' => $muestreo->observacion(),
        ];
    }
}
