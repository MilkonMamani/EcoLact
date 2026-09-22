<?php

namespace App\Http\Controllers\Web\Proveedor;

use App\Application\Acopio\ObtenerEntrega;
use App\Application\Acopio\ObtenerEntregasProveedor;
use App\Application\Calidad\ObtenerAnalisisProveedor;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Autoservicio del PROVEEDOR: siempre acota a sus propias entregas a partir
 * de $request->user()->proveedor->id, nunca de un proveedor_id externo. Los
 * filtros/KPIs se calculan en memoria (no hay una Query Eloquent dedicada):
 * el historial de un único proveedor es intrínsecamente pequeño, a
 * diferencia de los listados globales de ADMINISTRADOR/CALIDAD.
 */
final class EntregaController extends Controller
{
    public function __construct(
        private readonly ObtenerEntregasProveedor $obtenerEntregasProveedor,
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly ObtenerAnalisisProveedor $obtenerAnalisisProveedor,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EntregaModel::class);

        $proveedorId = $request->user()->proveedor?->id;
        $entregas = $proveedorId !== null ? $this->obtenerEntregasProveedor->execute($proveedorId) : [];

        $resultadosPorEntrega = $this->resultadosPorEntrega($request->user()->id);

        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($request->string('periodo')->toString() ?: null, $request);

        $filtradas = collect($entregas)->filter(function (Entrega $entrega) use ($request, $resultadosPorEntrega, $fechaDesde, $fechaHasta): bool {
            if ($fechaDesde !== null && $fechaHasta !== null && ($entrega->fechaHora() < $fechaDesde || $entrega->fechaHora() > $fechaHasta)) {
                return false;
            }

            if ($request->filled('estado') && $entrega->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            $resultado = $resultadosPorEntrega[$entrega->id()] ?? null;

            if ($request->filled('resultado')) {
                $filtroResultado = $request->string('resultado')->toString();

                if ($filtroResultado === 'sin_resultado' ? $resultado !== null : $resultado !== $filtroResultado) {
                    return false;
                }
            }

            return true;
        })->values();

        $kpis = [
            'entregas' => $filtradas->count(),
            // La leche de una muestra RECHAZADA nunca se acopió: no debe
            // sumar como litros entregados, aunque el registro se
            // conserve para el historial de calidad.
            'litros' => round((float) $filtradas->sum(
                fn (Entrega $e): float => ($resultadosPorEntrega[$e->id()] ?? null) === 'RECHAZADA' ? 0.0 : $e->litros()
            ), 2),
            'analizadas' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'ANALIZADA')->count(),
            'pendientes' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'PENDIENTE_ANALISIS')->count(),
        ];

        return view('proveedor.entregas.index', [
            'entregas' => $filtradas->all(),
            'kpis' => $kpis,
            'resultadosPorEntrega' => $resultadosPorEntrega,
        ]);
    }

    public function show(Request $request, EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        $analisis = collect($this->obtenerAnalisisProveedor->execute($request->user()->id))
            ->first(fn ($item) => $item->entregaId() === $entrega->id);

        return view('proveedor.entregas.show', [
            'entrega' => $this->obtenerEntrega->execute($entrega->id),
            'analisis' => $analisis,
        ]);
    }

    /**
     * @return array<int, ?string>
     */
    private function resultadosPorEntrega(int $userId): array
    {
        return collect($this->obtenerAnalisisProveedor->execute($userId))
            ->mapWithKeys(fn ($analisis) => [$analisis->entregaId() => $analisis->resultado()?->value])
            ->all();
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function rangoPeriodo(?string $periodo, Request $request): array
    {
        $hoy = CarbonImmutable::now(config('app.timezone'));

        return match ($periodo) {
            'hoy' => [$hoy->startOfDay(), $hoy->endOfDay()],
            'esta_semana' => [$hoy->startOfWeek(), $hoy->endOfWeek()],
            'este_mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                $request->filled('desde') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('desde')->toString())->startOfDay() : null,
                $request->filled('hasta') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('hasta')->toString())->endOfDay() : null,
            ],
            default => [null, null],
        };
    }
}
