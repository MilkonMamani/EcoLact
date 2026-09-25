<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\AsistenciaDelDiaQuery;
use App\Application\Pago\FilaAsistenciaDelDia;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentAsistenciaDelDiaQuery implements AsistenciaDelDiaQuery
{
    public function obtener(DateTimeImmutable $fecha, ?string $busqueda, ?int $rolId): array
    {
        $fechaSql = $fecha->format('Y-m-d');
        $hoySql = DateTimeImmutable::createFromInterface(now())->format('Y-m-d');

        $trabajadores = TrabajadorModel::query()
            ->where('estado', 'ACTIVO')
            ->whereDate('fecha_ingreso', '<=', $fechaSql)
            ->where(function (Builder $query) use ($fechaSql): void {
                $query->whereNull('fecha_salida')->orWhereDate('fecha_salida', '>=', $fechaSql);
            })
            ->when($busqueda !== null, function (Builder $query) use ($busqueda): void {
                $patron = '%'.$busqueda.'%';
                $query->where(function (Builder $grupo) use ($patron): void {
                    $grupo->where('nombres', 'like', $patron)
                        ->orWhere('apellidos', 'like', $patron)
                        ->orWhere('dni', 'like', $patron);
                });
            })
            ->when($rolId !== null, fn (Builder $query) => $query->whereHas('user', fn (Builder $sub) => $sub->where('role_id', $rolId)))
            ->with('user.role')
            ->get();

        $jornadasPorTrabajador = JornadaPersonalModel::query()
            ->whereIn('trabajador_id', $trabajadores->pluck('id'))
            ->whereDate('fecha', $fechaSql)
            ->get()
            ->keyBy('trabajador_id');

        return $trabajadores
            ->map(function (TrabajadorModel $trabajador) use ($jornadasPorTrabajador, $fechaSql, $hoySql): FilaAsistenciaDelDia {
                $jornada = $jornadasPorTrabajador->get($trabajador->id);
                $nombre = trim($trabajador->nombres.' '.$trabajador->apellidos);

                return new FilaAsistenciaDelDia(
                    trabajadorId: $trabajador->id,
                    userId: $trabajador->user_id,
                    nombre: $nombre !== '' ? $nombre : ($trabajador->user?->username ?? 'Sin nombre'),
                    iniciales: $this->iniciales($nombre !== '' ? $nombre : ($trabajador->user?->username ?? '?')),
                    rol: $trabajador->user?->role?->nombre ?? 'Personal',
                    dni: $trabajador->dni,
                    estado: $jornada?->estado,
                    estadoEtiqueta: $jornada !== null ? EstadoJornada::from($jornada->estado)->etiqueta() : null,
                    horaMarcacion: $jornada?->hora_marcacion?->format('H:i'),
                    origenEtiqueta: $jornada !== null ? OrigenJornada::from($jornada->origen)->etiqueta() : null,
                    esHoy: $fechaSql === $hoySql,
                    jornadaId: $jornada?->id,
                );
            })
            ->sortBy('nombre')
            ->values()
            ->all();
    }

    private function iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $iniciales = collect($partes)->take(2)->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)));

        return $iniciales->implode('') ?: '?';
    }
}
