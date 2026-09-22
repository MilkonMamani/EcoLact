<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentJornadaPersonalRepository implements JornadaPersonalRepository
{
    public function registrar(JornadaPersonal $jornada): JornadaPersonal
    {
        try {
            $model = JornadaPersonalModel::create([
                'user_id' => $jornada->userId(),
                'rol_id' => $jornada->rolId(),
                'fecha' => $jornada->fecha()->format('Y-m-d'),
                'observacion' => $jornada->observacion(),
                'registrado_por_user_id' => $jornada->registradoPorUserId(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Ya se registró un día trabajado para esta fecha.');
        }

        return $this->toEntity($model);
    }

    public function existeEnFecha(int $userId, DateTimeImmutable $fecha): bool
    {
        return JornadaPersonalModel::where('user_id', $userId)
            ->whereDate('fecha', $fecha->format('Y-m-d'))
            ->exists();
    }

    public function contarEnPeriodo(int $userId, DateTimeImmutable $inicio, DateTimeImmutable $fin): int
    {
        return JornadaPersonalModel::where('user_id', $userId)
            ->whereDate('fecha', '>=', $inicio->format('Y-m-d'))
            ->whereDate('fecha', '<=', $fin->format('Y-m-d'))
            ->count();
    }

    public function listarEnPeriodo(int $userId, DateTimeImmutable $inicio, DateTimeImmutable $fin): array
    {
        return JornadaPersonalModel::where('user_id', $userId)
            ->whereDate('fecha', '>=', $inicio->format('Y-m-d'))
            ->whereDate('fecha', '<=', $fin->format('Y-m-d'))
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (JornadaPersonalModel $model): JornadaPersonal => $this->toEntity($model))
            ->all();
    }

    private function toEntity(JornadaPersonalModel $model): JornadaPersonal
    {
        return new JornadaPersonal(
            id: $model->id,
            userId: $model->user_id,
            rolId: $model->rol_id,
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
            observacion: $model->observacion,
            registradoPorUserId: $model->registrado_por_user_id,
        );
    }
}
