<?php

namespace App\Http\Controllers\Web\Acopiador;

use App\Application\Acopio\ObtenerEntrega;
use App\Application\Acopio\ObtenerEntregas;
use App\Application\Acopio\RegistrarEntrega;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisPorZona;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\StoreEntregaRequest;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del ACOPIADOR: "Entregas" (Historial + Registrar entrega). Siempre
 * acota a la propia zona del acopiador autenticado ($request->user()
 * ->acopiador->zona_id), nunca a un ?zona_id= del cliente (ver fuga
 * cerrada en Etapa 10.1.2). Los filtros/KPIs se calculan en memoria: el
 * acopio de una sola zona es intrínsecamente pequeño, a diferencia de los
 * listados globales de ADMINISTRADOR/CALIDAD.
 */
final class EntregaController extends Controller
{
    public function __construct(
        private readonly RegistrarEntrega $registrarEntrega,
        private readonly ObtenerEntregas $obtenerEntregas,
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
        private readonly ObtenerAnalisisPorZona $obtenerAnalisisPorZona,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EntregaModel::class);

        $zonaId = $request->user()->acopiador?->zona_id;
        $entregas = $zonaId !== null ? $this->obtenerEntregas->execute($zonaId) : [];
        $resultadosPorEntrega = $zonaId !== null ? $this->resultadosPorEntrega($zonaId) : [];

        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($request->string('periodo')->toString() ?: null, $request);

        $filtradas = collect($entregas)->filter(function (Entrega $entrega) use ($request, $resultadosPorEntrega, $fechaDesde, $fechaHasta): bool {
            if ($fechaDesde !== null && $fechaHasta !== null && ($entrega->fechaHora() < $fechaDesde || $entrega->fechaHora() > $fechaHasta)) {
                return false;
            }

            if ($request->filled('proveedor_id') && $entrega->proveedorId() !== $request->integer('proveedor_id')) {
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
        })->sortByDesc(fn (Entrega $e) => $e->fechaHora())->values();

        // Nota: Entrega expone proveedorId() como método, no como propiedad
        // pública, así que pluck('proveedorId') devolvería silenciosamente
        // una lista vacía (mismo problema ya detectado en Stock/Compras).
        $proveedoresIds = collect($entregas)->map(fn (Entrega $e): int => $e->proveedorId())->unique();
        $proveedoresPorId = ProveedorModel::whereIn('id', $proveedoresIds)->get()
            ->mapWithKeys(fn (ProveedorModel $p): array => [$p->id => trim($p->nombres.' '.$p->apellidos)]);

        $kpis = [
            'entregas' => $filtradas->count(),
            'litros' => round((float) $filtradas->sum(fn (Entrega $e): float => $e->litros()), 2),
            'proveedores' => $filtradas->map(fn (Entrega $e): int => $e->proveedorId())->unique()->count(),
            'pendientes' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'PENDIENTE_ANALISIS')->count(),
        ];

        return view('acopiador.entregas.index', [
            'entregas' => $filtradas->all(),
            'kpis' => $kpis,
            'resultadosPorEntrega' => $resultadosPorEntrega,
            'proveedoresPorId' => $proveedoresPorId,
            'proveedoresDisponibles' => $proveedoresPorId->sort(),
        ]);
    }

    /**
     * Pantalla "Registrar entrega": acción operativa principal del
     * acopiador, separada del historial (no deben mezclarse en una sola
     * pantalla). Puede prellenar el DNI cuando se llega desde el detalle
     * de un proveedor ya identificado (?dni=...).
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', EntregaModel::class);

        return view('acopiador.entregas.create', [
            'dniPrefill' => $request->query('dni'),
        ]);
    }

    public function show(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        $entrega->load('proveedor');

        return view('acopiador.entregas.show', [
            'entrega' => $this->obtenerEntrega->execute($entrega->id),
            'proveedor' => $entrega->proveedor,
            'analisis' => $this->obtenerAnalisisCalidad->porEntrega($entrega->id),
        ]);
    }

    public function store(StoreEntregaRequest $request): RedirectResponse
    {
        Gate::authorize('create', EntregaModel::class);

        $datos = $request->validated();

        $this->registrarEntrega->execute(
            metodoIdentificacion: $datos['metodo_identificacion'],
            identificador: $datos['identificador'],
            acopiadorUserId: $request->user()->id,
            litros: (float) $datos['litros'],
            fechaHora: isset($datos['fecha_hora']) ? new DateTimeImmutable($datos['fecha_hora']) : null,
            observacion: $datos['observacion'] ?? null,
        );

        return redirect()->route('acopiador.entregas.index')->with('success', 'Entrega registrada correctamente.');
    }

    /**
     * @return array<int, ?string>
     */
    private function resultadosPorEntrega(int $zonaId): array
    {
        return collect($this->obtenerAnalisisPorZona->execute($zonaId))
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
            'ayer' => [$hoy->subDay()->startOfDay(), $hoy->subDay()->endOfDay()],
            'esta_semana' => [$hoy->startOfWeek(), $hoy->endOfWeek()],
            'semana_anterior' => [$hoy->subWeek()->startOfWeek(), $hoy->subWeek()->endOfWeek()],
            'este_mes' => [$hoy->startOfMonth(), $hoy->endOfMonth()],
            'personalizado' => [
                $request->filled('desde') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('desde')->toString())->startOfDay() : null,
                $request->filled('hasta') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('hasta')->toString())->endOfDay() : null,
            ],
            default => [null, null],
        };
    }
}
