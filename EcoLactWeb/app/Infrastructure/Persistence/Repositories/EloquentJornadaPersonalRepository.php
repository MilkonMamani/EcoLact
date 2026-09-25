<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
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
                'trabajador_id' => $jornada->trabajadorId(),
                'rol_id' => $jornada->rolId(),
                'fecha' => $jornada->fecha()->format('Y-m-d'),
                'estado' => $jornada->estado()->value,
                'origen' => $jornada->origen()->value,
                'hora_marcacion' => $jornada->horaMarcacion()?->format('Y-m-d H:i:s'),
                'observacion' => $jornada->observacion(),
                'registrado_por_user_id' => $jornada->registradoPorUserId(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Ya existe una jornada registrada para esta fecha.');
        }

        return $this->toEntity($model);
    }

    public function buscarPorId(int $id): ?JornadaPersonal
    {
        $model = JornadaPersonalModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function existeEnFecha(int $trabajadorId, DateTimeImmutable $fecha): bool
    {
        return JornadaPersonalModel::where('trabajador_id', $trabajadorId)
            ->whereDate('fecha', $fecha->format('Y-m-d'))
            ->exists();
    }

    public function buscarEnFecha(int $trabajadorId, DateTimeImmutable $fecha): ?JornadaPersonal
    {
        $model = JornadaPersonalModel::where('trabajador_id', $trabajadorId)
            ->whereDate('fecha', $fecha->format('Y-m-d'))
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function contarEnPeriodo(int $trabajadorId, DateTimeImmutable $inicio, DateTimeImmutable $fin, ?EstadoJornada $estado = null): int
    {
        return JornadaPersonalModel::where('trabajador_id', $trabajadorId)
            ->whereDate('fecha', '>=', $inicio->format('Y-m-d'))
            ->whereDate('fecha', '<=', $fin->format('Y-m-d'))
            ->when($estado !== null, fn ($query) => $query->where('estado', $estado->value))
            ->count();
    }

    public function listarEnPeriodo(int $trabajadorId, DateTimeImmutable $inicio, DateTimeImmutable $fin): array
    {
        return JornadaPersonalModel::where('trabajador_id', $trabajadorId)
            ->whereDate('fecha', '>=', $inicio->format('Y-m-d'))
            ->whereDate('fecha', '<=', $fin->format('Y-m-d'))
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (JornadaPersonalModel $model): JornadaPersonal => $this->toEntity($model))
            ->all();
    }

    public function actualizarEstado(int $jornadaId, EstadoJornada $nuevoEstado, OrigenJornada $origen, ?string $observacion): JornadaPersonal
    {
        $model = JornadaPersonalModel::findOrFail($jornadaId);

        $model->update([
            'estado' => $nuevoEstado->value,
            'origen' => $origen->value,
            'observacion' => $observacion,
        ]);

        return $this->toEntity($model->fresh());
    }

    private function toEntity(JornadaPersonalModel $model): JornadaPersonal
    {
        return new JornadaPersonal(
            id: $model->id,
            trabajadorId: $model->trabajador_id,
            rolId: $model->rol_id,
            fecha: DateTimeImmutable::createFromInterface($model->fecha),
            estado: EstadoJornada::from($model->estado),
            origen: OrigenJornada::from($model->origen),
            horaMarcacion: $model->hora_marcacion !== null ? DateTimeImmutable::createFromInterface($model->hora_marcacion) : null,
            observacion: $model->observacion,
            registradoPorUserId: $model->registrado_por_user_id,
        );
    }
}
