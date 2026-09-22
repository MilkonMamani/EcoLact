<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Comunicacion\Comunicado;
use App\Domain\Comunicacion\Repositories\ComunicadoRepository;
use App\Infrastructure\Persistence\Models\ComunicadoDestinatarioModel;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use DateTimeImmutable;

class EloquentComunicadoRepository implements ComunicadoRepository
{
    public function guardar(Comunicado $comunicado): Comunicado
    {
        $model = ComunicadoModel::create($this->toModelData($comunicado));

        return $this->toEntity($model);
    }

    public function actualizar(Comunicado $comunicado): Comunicado
    {
        $model = ComunicadoModel::findOrFail($comunicado->id());
        $model->update($this->toModelData($comunicado));

        return $this->toEntity($model->fresh());
    }

    public function buscarPorId(int $id): ?Comunicado
    {
        $model = ComunicadoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * @param  int[]  $userIds
     */
    public function agregarDestinatarios(int $comunicadoId, array $userIds): void
    {
        $ahora = now();

        $filas = array_map(
            static fn (int $userId): array => [
                'comunicado_id' => $comunicadoId,
                'user_id' => $userId,
                'leido' => false,
                'fecha_lectura' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            $userIds,
        );

        if ($filas !== []) {
            ComunicadoDestinatarioModel::insert($filas);
        }
    }

    /**
     * @return Comunicado[]
     */
    public function listarParaUsuario(int $userId): array
    {
        return ComunicadoModel::whereHas(
            'destinatarios',
            fn ($query) => $query->where('user_id', $userId)
        )
            ->get()
            ->map(fn (ComunicadoModel $model): Comunicado => $this->toEntity($model))
            ->all();
    }

    /**
     * @return Comunicado[]
     */
    public function listar(): array
    {
        return ComunicadoModel::orderByDesc('fecha_publicacion')
            ->get()
            ->map(fn (ComunicadoModel $model): Comunicado => $this->toEntity($model))
            ->all();
    }

    /**
     * @return list<array{userId: int, leido: bool, fechaLectura: ?DateTimeImmutable}>
     */
    public function listarDestinatarios(int $comunicadoId): array
    {
        return ComunicadoDestinatarioModel::where('comunicado_id', $comunicadoId)
            ->get()
            ->map(fn (ComunicadoDestinatarioModel $model): array => [
                'userId' => $model->user_id,
                'leido' => (bool) $model->leido,
                'fechaLectura' => $model->fecha_lectura ? DateTimeImmutable::createFromInterface($model->fecha_lectura) : null,
            ])
            ->all();
    }

    /**
     * @return array{leido: bool, fechaLectura: ?DateTimeImmutable}|null
     */
    public function buscarLecturaDestinatario(int $comunicadoId, int $userId): ?array
    {
        $destinatario = ComunicadoDestinatarioModel::where('comunicado_id', $comunicadoId)
            ->where('user_id', $userId)
            ->first();

        if ($destinatario === null) {
            return null;
        }

        return [
            'leido' => (bool) $destinatario->leido,
            'fechaLectura' => $destinatario->fecha_lectura ? DateTimeImmutable::createFromInterface($destinatario->fecha_lectura) : null,
        ];
    }

    public function marcarComoLeido(int $comunicadoId, int $userId): void
    {
        ComunicadoDestinatarioModel::where('comunicado_id', $comunicadoId)
            ->where('user_id', $userId)
            ->update(['leido' => true, 'fecha_lectura' => now()]);
    }

    /**
     * @return array<int, bool>
     */
    public function listarLecturaParaUsuario(int $userId): array
    {
        return ComunicadoDestinatarioModel::where('user_id', $userId)
            ->get(['comunicado_id', 'leido'])
            ->mapWithKeys(fn (ComunicadoDestinatarioModel $model): array => [$model->comunicado_id => (bool) $model->leido])
            ->all();
    }

    private function toEntity(ComunicadoModel $model): Comunicado
    {
        return new Comunicado(
            id: $model->id,
            titulo: $model->titulo,
            contenido: $model->contenido,
            tipo: $model->tipo,
            alcance: $model->alcance,
            zonaId: $model->zona_id,
            fechaPublicacion: DateTimeImmutable::createFromInterface($model->fecha_publicacion),
            fechaEvento: $model->fecha_evento ? DateTimeImmutable::createFromInterface($model->fecha_evento) : null,
            creadoPorUserId: $model->creado_por_user_id,
            estado: $model->estado,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toModelData(Comunicado $comunicado): array
    {
        return [
            'titulo' => $comunicado->titulo(),
            'contenido' => $comunicado->contenido(),
            'tipo' => $comunicado->tipo(),
            'alcance' => $comunicado->alcance(),
            'zona_id' => $comunicado->zonaId(),
            'fecha_publicacion' => $comunicado->fechaPublicacion()->format('Y-m-d H:i:s'),
            'fecha_evento' => $comunicado->fechaEvento()?->format('Y-m-d H:i:s'),
            'creado_por_user_id' => $comunicado->creadoPorUserId(),
            'estado' => $comunicado->estado(),
        ];
    }
}
