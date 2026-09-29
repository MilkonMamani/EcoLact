<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\AsistenciaDelDiaQuery;
use App\Application\Pago\FilaAsistenciaDelDia;
use App\Application\Pago\ResumenAsistenciaDelDia;
use App\Application\Shared\Reloj;
use App\Domain\Auth\Role;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Asistencia de una fecha: trabajadores vigentes que marcan asistencia, con
 * su jornada de ese día si existe.
 *
 * - paginar(): una página ordenada por nombre en SQL; el rol llega con
 *   eager loading (user.role) y las jornadas de la página en UNA consulta.
 * - resumen(): contadores de TODO el filtro (no solo de la página) con
 *   agregados SQL, sin cargar filas.
 */
final class EloquentAsistenciaDelDiaQuery implements AsistenciaDelDiaQuery
{
    public function __construct(private readonly Reloj $reloj) {}

    public function paginar(DateTimeImmutable $fecha, ?string $busqueda, ?int $rolId, int $porPagina, string $nombrePagina = 'pagina'): LengthAwarePaginator
    {
        $fechaSql = $fecha->format('Y-m-d');
        $esHoy = $fechaSql === $this->reloj->hoy()->format('Y-m-d');

        $pagina = $this->trabajadores($fechaSql, $busqueda, $rolId)
            ->with('user.role')
            ->orderBy('nombres')
            ->orderBy('apellidos')
            ->orderBy('id')
            ->paginate($porPagina, pageName: $nombrePagina)
            ->withQueryString();

        $jornadas = JornadaPersonalModel::query()
            ->whereIn('trabajador_id', $pagina->getCollection()->pluck('id'))
            ->whereDate('fecha', $fechaSql)
            ->get()
            ->keyBy('trabajador_id');

        return $pagina->through(fn (TrabajadorModel $trabajador): FilaAsistenciaDelDia => $this->fila($trabajador, $jornadas->get($trabajador->id), $esHoy));
    }

    public function resumen(DateTimeImmutable $fecha, ?string $busqueda, ?int $rolId): ResumenAsistenciaDelDia
    {
        $fechaSql = $fecha->format('Y-m-d');
        $trabajadores = $this->trabajadores($fechaSql, $busqueda, $rolId);

        $porEstado = JornadaPersonalModel::query()
            ->whereIn('trabajador_id', (clone $trabajadores)->select('id'))
            ->whereDate('fecha', $fechaSql)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $total = $trabajadores->count();
        $presentes = (int) ($porEstado[EstadoJornada::PRESENTE->value] ?? 0);
        $faltas = (int) ($porEstado[EstadoJornada::FALTA->value] ?? 0);

        return new ResumenAsistenciaDelDia(
            trabajadores: $total,
            presentes: $presentes,
            faltas: $faltas,
            sinRegistro: max(0, $total - $presentes - $faltas),
        );
    }

    /**
     * Personal vigente en la fecha (la fecha cae dentro de uno de sus periodos
     * de actividad, no según su estado actual: un reactivado no aparece en
     * días en que estuvo inactivo) y cuyo rol marca asistencia (el ADMINISTRADOR no).
     */
    private function trabajadores(string $fechaSql, ?string $busqueda, ?int $rolId): Builder
    {
        return TrabajadorModel::query()
            ->whereHas('periodos', function (Builder $periodo) use ($fechaSql): void {
                $periodo->whereDate('fecha_inicio', '<=', $fechaSql)
                    ->where(fn (Builder $fin) => $fin->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fechaSql));
            })
            ->whereHas('user.role', fn (Builder $rol) => $rol->where('controla_asistencia', true))
            ->when($busqueda !== null, function (Builder $query) use ($busqueda): void {
                $patron = '%'.$busqueda.'%';
                $query->where(function (Builder $grupo) use ($patron): void {
                    $grupo->where('nombres', 'like', $patron)
                        ->orWhere('apellidos', 'like', $patron)
                        ->orWhere('dni', 'like', $patron);
                });
            })
            ->when($rolId !== null, fn (Builder $query) => $query->whereHas('user', fn (Builder $sub) => $sub->where('role_id', $rolId)));
    }

    private function fila(TrabajadorModel $trabajador, ?JornadaPersonalModel $jornada, bool $esHoy): FilaAsistenciaDelDia
    {
        $nombre = trim($trabajador->nombres.' '.$trabajador->apellidos);
        $nombre = $nombre !== '' ? $nombre : ($trabajador->user?->username ?? 'Sin nombre');

        return new FilaAsistenciaDelDia(
            trabajadorId: $trabajador->id,
            userId: $trabajador->user_id,
            nombre: $nombre,
            iniciales: $this->iniciales($nombre),
            rol: $trabajador->user?->role?->nombre !== null ? Role::etiquetaDe($trabajador->user->role->nombre) : 'Personal',
            dni: $trabajador->dni,
            estado: $jornada?->estado,
            estadoEtiqueta: $jornada !== null ? EstadoJornada::from($jornada->estado)->etiqueta() : null,
            horaMarcacion: $jornada?->hora_marcacion?->format('H:i'),
            origenEtiqueta: $jornada !== null ? OrigenJornada::from($jornada->origen)->etiqueta() : null,
            esHoy: $esHoy,
            jornadaId: $jornada?->id,
            origen: $jornada?->origen,
        );
    }

    private function iniciales(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $iniciales = collect($partes)->take(2)->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)));

        return $iniciales->implode('') ?: '?';
    }
}
