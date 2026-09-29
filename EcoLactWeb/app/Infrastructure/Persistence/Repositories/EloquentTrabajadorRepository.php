<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Trabajador\PeriodoActividad;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Infrastructure\Persistence\Models\TrabajadorPeriodoActividadModel;
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

    public function buscarPorUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return TrabajadorModel::whereIn('user_id', $userIds)
            ->with('periodos')
            ->get()
            ->mapWithKeys(fn (TrabajadorModel $model): array => [(int) $model->user_id => $this->toEntity($model)])
            ->all();
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

    public function listarConActividadEntre(DateTimeImmutable $inicio, DateTimeImmutable $fin): array
    {
        return TrabajadorModel::query()
            ->whereHas('periodos', function ($query) use ($inicio, $fin): void {
                $query->whereDate('fecha_inicio', '<=', $fin->format('Y-m-d'))
                    ->where(fn ($sub) => $sub->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $inicio->format('Y-m-d')));
            })
            ->with('periodos')
            ->orderBy('id')
            ->get()
            ->map(fn (TrabajadorModel $model): Trabajador => $this->toEntity($model))
            ->all();
    }

    public function abrirPeriodo(int $trabajadorId, DateTimeImmutable $inicio): void
    {
        $inicio = $inicio->setTime(0, 0);
        $ultimo = TrabajadorPeriodoActividadModel::where('trabajador_id', $trabajadorId)->orderByDesc('fecha_inicio')->lockForUpdate()->first();

        if ($ultimo !== null && $ultimo->fecha_fin === null) {
            return;
        }

        if ($ultimo !== null && $ultimo->fecha_fin->format('Y-m-d') >= $inicio->modify('-1 day')->format('Y-m-d')) {
            $ultimo->update(['fecha_fin' => null]);

            return;
        }

        TrabajadorPeriodoActividadModel::create([
            'trabajador_id' => $trabajadorId,
            'fecha_inicio' => $inicio->format('Y-m-d'),
            'fecha_fin' => null,
        ]);
    }

    public function cerrarPeriodo(int $trabajadorId, DateTimeImmutable $fin): void
    {
        $abierto = TrabajadorPeriodoActividadModel::where('trabajador_id', $trabajadorId)->whereNull('fecha_fin')->lockForUpdate()->first();

        if ($abierto === null) {
            return;
        }

        if ($fin->format('Y-m-d') < $abierto->fecha_inicio->format('Y-m-d')) {
            $abierto->delete();

            return;
        }

        $abierto->update(['fecha_fin' => $fin->format('Y-m-d')]);
    }

    public function controlaAsistencia(int $trabajadorId): bool
    {
        $controla = TrabajadorModel::query()
            ->join('users', 'users.id', '=', 'trabajadores.user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('trabajadores.id', $trabajadorId)
            ->value('roles.controla_asistencia');

        return $controla === null || (bool) $controla;
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
            periodos: $model->periodos
                ->map(fn (TrabajadorPeriodoActividadModel $periodo): PeriodoActividad => new PeriodoActividad(
                    DateTimeImmutable::createFromInterface($periodo->fecha_inicio),
                    $periodo->fecha_fin !== null ? DateTimeImmutable::createFromInterface($periodo->fecha_fin) : null,
                ))
                ->all(),
        );
    }
}
