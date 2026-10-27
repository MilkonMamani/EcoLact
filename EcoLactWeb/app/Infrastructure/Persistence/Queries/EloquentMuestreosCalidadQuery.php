<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Calidad\Contracts\MuestreosCalidadQuery;
use App\Domain\Entrega\Entrega;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoMuestreo;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\MuestreoCalidadModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;

/**
 * Consultas del muestreo de calidad, unidas en una sola consulta a proveedor,
 * zona y acopiador (sin N+1). Las fechas salen en ISO-8601 con zona horaria
 * para que el móvil muestre la hora local real.
 */
final class EloquentMuestreosCalidadQuery implements MuestreosCalidadQuery
{
    public function pendientes(?string $busqueda = null, ?int $zonaId = null): array
    {
        return $this->filtrar($this->muestras(), $busqueda, $zonaId)
            ->whereIn('m.estado', [EstadoMuestreo::SELECCIONADA->value, EstadoMuestreo::EN_ANALISIS->value])
            ->where('e.estado', '<>', EstadoEntrega::ANULADA->value)
            ->orderBy('m.fecha_seleccion')
            ->orderBy('m.id')
            ->get()
            ->map(fn (object $fila): array => $this->filaMuestra($fila))
            ->all();
    }

    public function muestra(int $muestreoId): ?array
    {
        $fila = $this->muestras()->where('m.id', $muestreoId)->first();

        return $fila !== null ? $this->filaMuestra($fila) : null;
    }

    public function muestraPorEntrega(int $entregaId): ?array
    {
        $fila = $this->muestras()->where('m.entrega_id', $entregaId)->first();

        return $fila !== null ? $this->filaMuestra($fila) : null;
    }

    public function kpis(DateTimeImmutable $hoy): array
    {
        $dia = CarbonImmutable::instance($hoy)->setTimezone(config('app.timezone'));

        return [
            'pendientes' => MuestreoCalidadModel::query()
                ->whereIn('estado', [EstadoMuestreo::SELECCIONADA->value, EstadoMuestreo::EN_ANALISIS->value])
                ->count(),
            // Por la hora real del análisis (fecha_analisis), no por la de sincronización.
            'analizadas_hoy' => MuestreoCalidadModel::query()->from('muestreos_calidad as m')
                ->join('analisis_calidad as a', 'a.entrega_id', '=', 'm.entrega_id')
                ->where('m.estado', EstadoMuestreo::ANALIZADA->value)
                ->where('a.estado', EstadoAnalisis::CONFIRMADO->value)
                ->whereBetween('a.fecha_analisis', [$dia->startOfDay()->toDateTimeString(), $dia->endOfDay()->toDateTimeString()])
                ->count(),
        ];
    }

    public function estadosPorEntrega(array $entregaIds): array
    {
        if ($entregaIds === []) {
            return [];
        }

        return MuestreoCalidadModel::query()
            ->whereIn('entrega_id', $entregaIds)
            ->pluck('estado', 'entrega_id')
            ->mapWithKeys(fn ($estado, $entregaId): array => [(int) $entregaId => (string) $estado])
            ->all();
    }

    public function zonas(): array
    {
        return ZonaModel::query()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])
            ->all();
    }

    public function elegibles(?string $busqueda, ?int $zonaId, DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $query = $this->filtrar($this->entregasElegibles($zonaId, $desde, $hasta), $busqueda, null);

        return $this->columnasEntrega($query)
            ->orderByDesc('e.fecha_hora')
            ->orderByDesc('e.id')
            ->limit(200)
            ->get()
            ->map(fn (object $fila): array => $this->filaEntrega($fila))
            ->all();
    }

    public function idsElegibles(?int $zonaId, DateTimeImmutable $desde, DateTimeImmutable $hasta, array $excluir = []): array
    {
        $query = $this->entregasElegibles($zonaId, $desde, $hasta);

        if ($excluir !== []) {
            $query->whereNotIn('e.id', $excluir);
        }

        return $query->orderBy('e.id')->pluck('e.id')->map(fn ($id): int => (int) $id)->all();
    }

    public function entrega(int $entregaId): ?array
    {
        $query = $this->baseEntregas()->where('e.id', $entregaId);
        $fila = $this->columnasEntrega($query)->first();

        return $fila !== null ? $this->filaEntrega($fila) : null;
    }

    private function filtrar(Builder $query, ?string $busqueda, ?int $zonaId): Builder
    {
        if ($busqueda !== null && trim($busqueda) !== '') {
            $termino = '%'.trim($busqueda).'%';
            $query->where(function (Builder $query) use ($termino): void {
                $query->where('p.nombres', 'like', $termino)
                    ->orWhere('p.apellidos', 'like', $termino)
                    ->orWhere('p.dni', 'like', $termino);
            });
        }

        if ($zonaId !== null) {
            $query->where('e.zona_id', $zonaId);
        }

        return $query;
    }

    private function baseEntregas(): Builder
    {
        return EntregaModel::query()->from('entregas as e')->toBase()
            ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
            ->leftJoin('zonas as z', 'z.id', '=', 'e.zona_id')
            ->leftJoin('acopiadores as ac', 'ac.id', '=', 'e.acopiador_id');
    }

    private function entregasElegibles(?int $zonaId, DateTimeImmutable $desde, DateTimeImmutable $hasta): Builder
    {
        $query = $this->baseEntregas()
            ->leftJoin('muestreos_calidad as m', 'm.entrega_id', '=', 'e.id')
            ->whereNull('m.id')
            ->where('e.estado', EstadoEntrega::PENDIENTE_ANALISIS->value)
            ->where('p.estado', EstadoUsuario::ACTIVO->value)
            ->whereBetween('e.fecha_hora', [$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')]);

        if ($zonaId !== null) {
            $query->where('e.zona_id', $zonaId);
        }

        return $query;
    }

    private function columnasEntrega(Builder $query): Builder
    {
        return $query->select(
            'e.id as entrega_id',
            'e.fecha_hora',
            'e.litros',
            'e.estado as entrega_estado',
            'p.nombres',
            'p.apellidos',
            'p.dni',
            'z.nombre as zona_nombre',
            'ac.nombres as acopiador_nombres',
            'ac.apellidos as acopiador_apellidos',
        );
    }

    private function muestras(): Builder
    {
        return $this->columnasEntrega(
            MuestreoCalidadModel::query()->from('muestreos_calidad as m')->toBase()
                ->join('entregas as e', 'e.id', '=', 'm.entrega_id')
                ->join('proveedores as p', 'p.id', '=', 'e.proveedor_id')
                ->leftJoin('zonas as z', 'z.id', '=', 'e.zona_id')
                ->leftJoin('acopiadores as ac', 'ac.id', '=', 'e.acopiador_id'),
        )->addSelect(
            'm.id as muestreo_id',
            'm.metodo_seleccion',
            'm.tipo_control',
            'm.fecha_seleccion',
            'm.fecha_captura_local',
            'm.estado as muestreo_estado',
            'm.observacion as muestreo_observacion',
        );
    }

    /** @return array<string, mixed> */
    private function filaEntrega(object $fila): array
    {
        $fechaHora = CarbonImmutable::parse($fila->fecha_hora, config('app.timezone'));

        return [
            'entregaId' => (int) $fila->entrega_id,
            'proveedorNombre' => trim($fila->nombres.' '.$fila->apellidos),
            'proveedorDni' => $fila->dni,
            'zonaNombre' => $fila->zona_nombre,
            'acopiadorNombre' => $fila->acopiador_nombres !== null ? trim($fila->acopiador_nombres.' '.$fila->acopiador_apellidos) : null,
            'litros' => (float) $fila->litros,
            'fechaHora' => $fechaHora->format(DATE_ATOM),
            // Solo informativo: pasado este instante los litros quedan confirmados.
            // NO limita el muestreo ni el análisis de calidad.
            'litrosEditablesHasta' => $fechaHora->addMinutes(Entrega::MINUTOS_EDICION_LITROS)->format(DATE_ATOM),
            'entregaEstado' => $fila->entrega_estado,
        ];
    }

    /** @return array<string, mixed> */
    private function filaMuestra(object $fila): array
    {
        return [
            'id' => (int) $fila->muestreo_id,
            ...$this->filaEntrega($fila),
            'metodoSeleccion' => $fila->metodo_seleccion,
            'tipoControl' => $fila->tipo_control,
            'fechaSeleccion' => CarbonImmutable::parse($fila->fecha_seleccion, config('app.timezone'))->format(DATE_ATOM),
            'fechaCapturaLocal' => $fila->fecha_captura_local !== null
                ? CarbonImmutable::parse($fila->fecha_captura_local, config('app.timezone'))->format(DATE_ATOM)
                : null,
            'estado' => $fila->muestreo_estado,
            'observacion' => $fila->muestreo_observacion,
        ];
    }
}
