<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use DateTimeImmutable;

final class EloquentTrabajadorRepository implements TrabajadorRepository
{
    public function guardar(Trabajador $trabajador): Trabajador
    {
        $model = TrabajadorModel::updateOrCreate(
            ['user_id' => $trabajador->userId()],
            [
                'dni' => $trabajador->dni(),
                'nombres' => $trabajador->nombres(),
                'apellidos' => $trabajador->apellidos(),
                'telefono' => $trabajador->telefono(),
                'estado' => $trabajador->estado()->value,
                'fecha_ingreso' => $trabajador->fechaIngreso()->format('Y-m-d'),
                'fecha_salida' => $trabajador->fechaSalida()?->format('Y-m-d'),
                'qr_token' => $trabajador->qrToken(),
            ],
        );

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?Trabajador
    {
        $model = TrabajadorModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorUserId(int $userId): ?Trabajador
    {
        $model = TrabajadorModel::where('user_id', $userId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function buscarPorQrToken(string $qrToken): ?Trabajador
    {
        $model = TrabajadorModel::where('qr_token', $qrToken)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listarActivos(): array
    {
        return TrabajadorModel::where('estado', EstadoUsuario::ACTIVO->value)
            ->orderBy('id')
            ->get()
            ->map(fn (TrabajadorModel $model): Trabajador => $this->toEntity($model))
            ->all();
    }

    public function listarActivosVigentesEn(DateTimeImmutable $fecha): array
    {
        $dia = $fecha->format('Y-m-d');

        return TrabajadorModel::where('estado', EstadoUsuario::ACTIVO->value)
            ->whereDate('fecha_ingreso', '<=', $dia)
            ->where(function ($query) use ($dia) {
                $query->whereNull('fecha_salida')->orWhereDate('fecha_salida', '>=', $dia);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (TrabajadorModel $model): Trabajador => $this->toEntity($model))
            ->all();
    }

    private function toEntity(TrabajadorModel $model): Trabajador
    {
        return new Trabajador(
            id: $model->id,
            userId: $model->user_id,
            dni: $model->dni,
            nombres: $model->nombres,
            apellidos: $model->apellidos,
            telefono: $model->telefono,
            estado: EstadoUsuario::from($model->estado),
            fechaIngreso: DateTimeImmutable::createFromInterface($model->fecha_ingreso),
            fechaSalida: $model->fecha_salida !== null ? DateTimeImmutable::createFromInterface($model->fecha_salida) : null,
            qrToken: $model->qr_token,
        );
    }
}
