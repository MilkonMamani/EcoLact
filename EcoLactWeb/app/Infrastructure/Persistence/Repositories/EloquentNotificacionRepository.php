<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Infrastructure\Persistence\Models\NotificacionModel;
use DateTimeImmutable;

class EloquentNotificacionRepository implements NotificacionRepository
{
    public function guardar(Notificacion $notificacion): Notificacion
    {
        $model = NotificacionModel::create($this->toModelData($notificacion));

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Notificacion
    {
        $model = NotificacionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @return Notificacion[]
     */
    public function listarParaUsuario(int $userId): array
    {
        return NotificacionModel::where('user_id', $userId)
            ->get()
            ->map(fn (NotificacionModel $model): Notificacion => $this->toEntity($model))
            ->all();
    }

    public function marcarComoLeida(int $id): Notificacion
    {
        $model = NotificacionModel::findOrFail($id);
        $model->leido = true;
        $model->fecha_lectura = now();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(NotificacionModel $model): Notificacion
    {
        return new Notificacion(
            id: $model->id,
            userId: $model->user_id,
            tipo: $model->tipo,
            titulo: $model->titulo,
            mensaje: $model->mensaje,
            referenciaTipo: $model->referencia_tipo,
            referenciaId: $model->referencia_id,
            leido: (bool) $model->leido,
            fechaLectura: $model->fecha_lectura ? DateTimeImmutable::createFromInterface($model->fecha_lectura) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Notificacion $notificacion): array
    {
        return [
            'user_id' => $notificacion->userId(),
            'tipo' => $notificacion->tipo(),
            'titulo' => $notificacion->titulo(),
            'mensaje' => $notificacion->mensaje(),
            'referencia_tipo' => $notificacion->referenciaTipo(),
            'referencia_id' => $notificacion->referenciaId(),
            'leido' => $notificacion->leido(),
            'fecha_lectura' => $notificacion->fechaLectura()?->format('Y-m-d H:i:s'),
        ];
    }
}
