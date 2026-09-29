<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\Contracts\EntregasCalidadListadoQuery;
use App\Application\Calidad\EntregasCalidadListadoData;
use App\Application\Calidad\EntregasCalidadPaginaData;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/**
 * Calidad parte de las ENTREGAS (LEFT JOIN a analisis_calidad), no de los
 * análisis: así las entregas sin análisis aparecen como Pendiente sin crear un
 * análisis ficticio. El análisis sigue siendo opcional: Pendiente solo significa
 * "todavía no tiene análisis de calidad" y no bloquea el pago ni el stock.
 *
 * El analista de una fila es quien CONFIRMÓ el análisis
 * (analisis_calidad.confirmado_por_user_id → analistas_calidad.user_id); si lo
 * confirmó un usuario sin ficha de analista (p. ej. el administrador) se
 * muestra su username. Un BORRADOR todavía no tiene analista.
 */
final class EloquentEntregasCalidadListadoQuery implements EntregasCalidadListadoQuery
{
    public function obtener(AnalisisCalidadFiltro $filtro): EntregasCalidadListadoData
    {
        $confirmado = EstadoAnalisis::CONFIRMADO->value;

        $totales = $this->base($filtro)
            ->selectRaw('COUNT(CASE WHEN a.id IS NULL OR a.estado <> ? THEN 1 END) as pendientes', [$confirmado])
            ->selectRaw('COUNT(CASE WHEN a.estado = ? THEN 1 END) as analizadas', [$confirmado])
            ->selectRaw('COUNT(CASE WHEN a.estado = ? AND a.resultado = ? THEN 1 END) as aceptadas', [$confirmado, ResultadoCalidad::ACEPTADA->value])
            ->selectRaw('COUNT(CASE WHEN a.estado = ? AND a.resultado = ? THEN 1 END) as observadas', [$confirmado, ResultadoCalidad::OBSERVADA->value])
            ->selectRaw('COUNT(CASE WHEN a.estado = ? AND a.resultado = ? THEN 1 END) as rechazadas', [$confirmado, ResultadoCalidad::RECHAZADA->value])
            ->first();

        $filas = $this->filas($filtro)->get()->map(fn (object $fila): array => $this->fila($fila))->all();

        return new EntregasCalidadListadoData(
            kpis: [
                'pendientes' => (int) $totales->pendientes,
                'analizadas' => (int) $totales->analizadas,
                'aceptadas' => (int) $totales->aceptadas,
                'observadas' => (int) $totales->observadas,
                'rechazadas' => (int) $totales->rechazadas,
            ],
            zonas: $this->zonas(),
            filas: $filas,
        );
    }

    public function paginar(AnalisisCalidadFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): EntregasCalidadPaginaData
    {
        $filas = $this->filas($filtro)
            ->paginate($porPagina, pageName: $nombrePagina)
            ->withQueryString()
            ->through(fn (object $fila): array => $this->fila($fila));

        return new EntregasCalidadPaginaData(zonas: $this->zonas(), analistas: $this->analistas(), filas: $filas);
    }

    /** Filas ya unidas a proveedor, zona, análisis y analista: una sola consulta, sin N+1. */
    private function filas(AnalisisCalidadFiltro $filtro): Builder
    {
        $query = $this->base($filtro)
            ->leftJoin('users as u', 'u.id', '=', 'a.confirmado_por_user_id')
            ->leftJoin('analistas_calidad as ac', 'ac.user_id', '=', 'a.confirmado_por_user_id')
            ->select(
                'e.id as entrega_id',
                'e.fecha_hora',
                'e.litros',
                'p.nombres',
                'p.apellidos',
                'p.dni',
                'z.nombre as zona_nombre',
                'a.id as analisis_id',
                'a.estado',
                'a.resultado',
                'u.username as analista_username',
                'ac.nombres as analista_nombres',
                'ac.apellidos as analista_apellidos',
            );

        $direccion = $filtro->direccion;

        match ($filtro->orden) {
            'proveedor' => $query->orderBy('p.nombres', $direccion)->orderBy('p.apellidos', $direccion),
            // Solo un análisis CONFIRMADO tiene resultado: el resto (pendiente o borrador) va al final.
            'resultado' => $query
                ->orderByRaw(
                    'CASE WHEN a.estado = ? AND a.resultado = ? THEN 1 WHEN a.estado = ? AND a.resultado = ? THEN 2 WHEN a.estado = ? AND a.resultado = ? THEN 3 ELSE 4 END '.($direccion === 'asc' ? 'ASC' : 'DESC'),
                    [
                        EstadoAnalisis::CONFIRMADO->value, ResultadoCalidad::ACEPTADA->value,
                        EstadoAnalisis::CONFIRMADO->value, ResultadoCalidad::OBSERVADA->value,
                        EstadoAnalisis::CONFIRMADO->value, ResultadoCalidad::RECHAZADA->value,
                    ],
                ),
            default => $query->orderBy('e.fecha_hora', $direccion),
        };

        return $query->orderByDesc('e.fecha_hora')->orderByDesc('e.id');
    }

    /**
     * @return array{entregaId: int, analisisId: ?int, fecha: string, proveedorNombre: string, proveedorDni: string, zonaNombre: string, resultado: ?string, estado: string, litros: float, analistaNombre: ?string}
     */
    private function fila(object $fila): array
    {
        $confirmado = $fila->estado === EstadoAnalisis::CONFIRMADO->value;

        return [
            'entregaId' => (int) $fila->entrega_id,
            'analisisId' => $fila->analisis_id !== null ? (int) $fila->analisis_id : null,
            'fecha' => CarbonImmutable::parse($fila->fecha_hora, config('app.timezone'))->format('d/m/Y H:i'),
            'proveedorNombre' => trim($fila->nombres.' '.$fila->apellidos),
            'proveedorDni' => $fila->dni,
            'zonaNombre' => $fila->zona_nombre,
            'resultado' => $confirmado ? $fila->resultado : null,
            'estado' => $fila->estado ?? 'PENDIENTE',
            'litros' => (float) $fila->litros,
            'analistaNombre' => match (true) {
                ! $confirmado => null,
                $fila->analista_nombres !== null => trim($fila->analista_nombres.' '.$fila->analista_apellidos),
                default => $fila->analista_username,
            },
        ];
    }

    private function base(AnalisisCalidadFiltro $filtro): Builder
    {
        $query = EntregaModel::query()->from('entregas as e')->toBase()
            ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->join('zonas as z', 'z.id', '=', 'e.zona_id')
            ->leftJoin('analisis_calidad as a', 'a.entrega_id', '=', 'e.id')
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value);

        if ($filtro->busqueda !== null) {
            $busqueda = '%'.$filtro->busqueda.'%';
            $codigoEntrega = ltrim($filtro->busqueda, '#');
            $query->where(function (Builder $query) use ($busqueda, $codigoEntrega): void {
                $query->where('p.nombres', 'like', $busqueda)
                    ->orWhere('p.apellidos', 'like', $busqueda)
                    ->orWhere('p.dni', 'like', $busqueda)
                    ->orWhere('e.id', $codigoEntrega);
            });
        }

        if ($filtro->zonaId !== null) {
            $query->where('e.zona_id', $filtro->zonaId);
        }

        if ($filtro->resultado !== null) {
            $query->where('a.estado', EstadoAnalisis::CONFIRMADO->value)->where('a.resultado', $filtro->resultado);
        }

        if ($filtro->estado === 'PENDIENTE') {
            $query->whereNull('a.id');
        } elseif ($filtro->estado !== null) {
            $query->where('a.estado', $filtro->estado);
        }

        if ($filtro->analistaUserId !== null) {
            $query->where('a.estado', EstadoAnalisis::CONFIRMADO->value)->where('a.confirmado_por_user_id', $filtro->analistaUserId);
        }

        if ($filtro->fechaDesde !== null && $filtro->fechaHasta !== null) {
            $query->whereBetween('e.fecha_hora', [
                $filtro->fechaDesde->toDateTimeString(),
                $filtro->fechaHasta->toDateTimeString(),
            ]);
        }

        return $query;
    }

    /** @return list<array{id: int, nombre: string}> */
    private function zonas(): array
    {
        return ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])
            ->all();
    }

    /**
     * Opciones del filtro Analista: la ficha real de cada analista de calidad,
     * identificada por su usuario (el que queda en confirmado_por_user_id).
     *
     * @return list<array{id: int, nombre: string}>
     */
    private function analistas(): array
    {
        return AnalistaCalidadModel::query()->orderBy('nombres')->orderBy('apellidos')->get(['user_id', 'nombres', 'apellidos'])
            ->map(fn (AnalistaCalidadModel $analista): array => ['id' => (int) $analista->user_id, 'nombre' => trim($analista->nombres.' '.$analista->apellidos)])
            ->all();
    }
}
