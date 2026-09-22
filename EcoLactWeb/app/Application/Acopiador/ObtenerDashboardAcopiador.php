<?php

namespace App\Application\Acopiador;

use App\Application\Acopio\ObtenerEntregas;
use App\Application\Calidad\ObtenerAnalisisPorZona;
use App\Application\Comunicacion\ObtenerComunicados;
use App\Domain\Entrega\Entrega;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Carbon\CarbonImmutable;

/**
 * Compone el Dashboard del acopiador reutilizando los Use Cases ya
 * existentes de Entregas, Calidad y Comunicados, sin duplicar su lógica.
 * A diferencia del Dashboard del proveedor (orientado a periodos de
 * liquidación semanales), aquí "Hoy" es el valor inicial: la actividad del
 * acopiador es operativa y diaria.
 */
final class ObtenerDashboardAcopiador
{
    private const MAX_ENTREGAS_PREVIA = 5;

    private const MAX_COMUNICADOS_PREVIA = 3;

    public function __construct(
        private readonly ObtenerEntregas $obtenerEntregas,
        private readonly ObtenerAnalisisPorZona $obtenerAnalisisPorZona,
        private readonly ObtenerComunicados $obtenerComunicados,
    ) {}

    public function execute(int $userId, int $zonaId, ?string $periodo, ?string $desde, ?string $hasta): DashboardAcopiadorData
    {
        $entregas = $this->obtenerEntregas->execute($zonaId);
        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($periodo, $desde, $hasta);

        $filtradas = collect($entregas)->filter(
            fn (Entrega $entrega): bool => $fechaDesde === null || $fechaHasta === null
                || ($entrega->fechaHora() >= $fechaDesde && $entrega->fechaHora() <= $fechaHasta)
        )->sortByDesc(fn (Entrega $e) => $e->fechaHora())->values();

        $resultadosPorEntrega = collect($this->obtenerAnalisisPorZona->execute($zonaId))
            ->mapWithKeys(fn ($analisis) => [$analisis->entregaId() => $analisis->resultado()?->value])
            ->all();

        $pendientes = $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'PENDIENTE_ANALISIS')->count();

        $proveedoresIds = $filtradas->map(fn (Entrega $e): int => $e->proveedorId())->unique();
        $nombresPorId = ProveedorModel::whereIn('id', $proveedoresIds)->get()
            ->mapWithKeys(fn (ProveedorModel $p): array => [$p->id => trim($p->nombres.' '.$p->apellidos)]);

        $proveedoresAtendidos = $filtradas->groupBy(fn (Entrega $e) => $e->proveedorId())
            ->map(fn ($grupo, $proveedorId): array => [
                'proveedorId' => (int) $proveedorId,
                'nombre' => $nombresPorId[$proveedorId] ?? ('Proveedor #'.$proveedorId),
                'entregas' => $grupo->count(),
            ])
            ->sortByDesc('entregas')
            ->values()
            ->all();

        $comunicados = $this->obtenerComunicados->execute($userId);
        usort($comunicados, static fn ($a, $b): int => $b->fechaPublicacion() <=> $a->fechaPublicacion());

        return new DashboardAcopiadorData(
            kpis: [
                'entregas' => $filtradas->count(),
                'litros' => round((float) $filtradas->sum(fn (Entrega $e): float => $e->litros()), 2),
                'proveedoresAtendidos' => count($proveedoresAtendidos),
                'pendientesAnalisis' => $pendientes,
            ],
            ultimasEntregas: $filtradas->take(self::MAX_ENTREGAS_PREVIA)->all(),
            resultadosPorEntrega: $resultadosPorEntrega,
            proveedoresAtendidos: $proveedoresAtendidos,
            comunicadosRecientes: array_slice($comunicados, 0, self::MAX_COMUNICADOS_PREVIA),
        );
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function rangoPeriodo(?string $periodo, ?string $desde, ?string $hasta): array
    {
        $hoy = CarbonImmutable::now(config('app.timezone'));

        return match ($periodo) {
            'ayer' => [$hoy->subDay()->startOfDay(), $hoy->subDay()->endOfDay()],
            'esta_semana' => [$hoy->startOfWeek(), $hoy->endOfWeek()],
            'semana_anterior' => [$hoy->subWeek()->startOfWeek(), $hoy->subWeek()->endOfWeek()],
            'personalizado' => [
                $desde !== null && $desde !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $desde)->startOfDay() : null,
                $hasta !== null && $hasta !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $hasta)->endOfDay() : null,
            ],
            // 'hoy' y el valor por defecto (sin selección): la actividad del
            // acopiador es operativa y diaria, así que "Hoy" es el punto de
            // partida (a diferencia del Dashboard del proveedor).
            default => [$hoy->startOfDay(), $hoy->endOfDay()],
        };
    }
}
