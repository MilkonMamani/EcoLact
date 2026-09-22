<?php

namespace App\Http\Controllers\Web\Acopiador;

use App\Application\Acopio\ObtenerEntregas;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisPorZona;
use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del ACOPIADOR: "Seguimiento de calidad". SOLO LECTURA: el
 * acopiador consulta el estado y resultado de calidad de las entregas de su
 * zona, pero nunca registra ni modifica un análisis técnico (eso es
 * exclusivo de CALIDAD, ver AnalisisCalidadPolicy::create/update).
 */
final class SeguimientoCalidadController extends Controller
{
    public function __construct(
        private readonly ObtenerEntregas $obtenerEntregas,
        private readonly ObtenerAnalisisPorZona $obtenerAnalisisPorZona,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $zonaId = $request->user()->acopiador?->zona_id;
        $entregas = $zonaId !== null ? $this->obtenerEntregas->execute($zonaId) : [];

        /** @var Collection<int, AnalisisCalidad> $analisisPorEntrega */
        $analisisPorEntrega = $zonaId !== null
            ? collect($this->obtenerAnalisisPorZona->execute($zonaId))->keyBy(fn (AnalisisCalidad $a) => $a->entregaId())
            : collect();

        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($request->string('periodo')->toString() ?: null, $request);

        $filtradas = collect($entregas)->filter(function (Entrega $entrega) use ($request, $analisisPorEntrega, $fechaDesde, $fechaHasta): bool {
            if ($fechaDesde !== null && $fechaHasta !== null && ($entrega->fechaHora() < $fechaDesde || $entrega->fechaHora() > $fechaHasta)) {
                return false;
            }

            if ($request->filled('proveedor_id') && $entrega->proveedorId() !== $request->integer('proveedor_id')) {
                return false;
            }

            if ($request->filled('estado_analisis') && $entrega->estado()->value !== $request->string('estado_analisis')->toString()) {
                return false;
            }

            $resultado = $analisisPorEntrega->get($entrega->id())?->resultado()?->value;

            if ($request->filled('resultado')) {
                $filtroResultado = $request->string('resultado')->toString();

                if ($filtroResultado === 'sin_resultado' ? $resultado !== null : $resultado !== $filtroResultado) {
                    return false;
                }
            }

            return true;
        })->sortByDesc(fn (Entrega $e) => $e->fechaHora())->values();

        $proveedoresIds = collect($entregas)->map(fn (Entrega $e): int => $e->proveedorId())->unique();
        $proveedoresPorId = ProveedorModel::whereIn('id', $proveedoresIds)->get()
            ->mapWithKeys(fn (ProveedorModel $p): array => [$p->id => trim($p->nombres.' '.$p->apellidos)]);

        $kpis = [
            'pendientes' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'PENDIENTE_ANALISIS')->count(),
            'analizadas' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'ANALIZADA')->count(),
            'aceptadas' => $filtradas->filter(fn (Entrega $e): bool => $analisisPorEntrega->get($e->id())?->resultado()?->value === 'ACEPTADA')->count(),
            'conObservaciones' => $filtradas->filter(
                fn (Entrega $e): bool => in_array($analisisPorEntrega->get($e->id())?->resultado()?->value, ['OBSERVADA', 'RECHAZADA'], true)
            )->count(),
        ];

        return view('acopiador.seguimiento-calidad.index', [
            'entregas' => $filtradas->all(),
            'analisisPorEntrega' => $analisisPorEntrega,
            'proveedoresPorId' => $proveedoresPorId,
            'kpis' => $kpis,
        ]);
    }

    public function show(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        $entrega->load('proveedor');

        return view('acopiador.seguimiento-calidad.show', [
            'entrega' => $entrega,
            'analisis' => $this->obtenerAnalisisCalidad->porEntrega($entrega->id),
        ]);
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function rangoPeriodo(?string $periodo, Request $request): array
    {
        $hoy = CarbonImmutable::now(config('app.timezone'));

        return match ($periodo) {
            'hoy' => [$hoy->startOfDay(), $hoy->endOfDay()],
            'ayer' => [$hoy->subDay()->startOfDay(), $hoy->subDay()->endOfDay()],
            'semana_anterior' => [$hoy->subWeek()->startOfWeek(), $hoy->subWeek()->endOfWeek()],
            'este_mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                $request->filled('desde') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('desde')->toString())->startOfDay() : null,
                $request->filled('hasta') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('hasta')->toString())->endOfDay() : null,
            ],
            // 'esta_semana' es el valor inicial del selector.
            default => [$hoy->startOfWeek(), $hoy->endOfWeek()],
        };
    }
}
