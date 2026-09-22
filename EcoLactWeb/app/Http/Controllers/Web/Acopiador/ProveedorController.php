<?php

namespace App\Http\Controllers\Web\Acopiador;

use App\Application\Calidad\ObtenerAnalisisPorProveedor;
use App\Application\Proveedor\ObtenerProveedor;
use App\Application\Proveedor\ObtenerProveedores;
use App\Domain\Proveedor\Proveedor;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del ACOPIADOR: "Proveedores de mi zona". Consulta y apoyo
 * operativo (identificar al proveedor para registrar una entrega), NUNCA
 * administración (crear/editar/desactivar sigue siendo exclusivo de
 * ADMINISTRADOR, ver Web\Admin\ProveedorController).
 */
final class ProveedorController extends Controller
{
    public function __construct(
        private readonly ObtenerProveedor $obtenerProveedor,
        private readonly ObtenerProveedores $obtenerProveedores,
        private readonly ObtenerAnalisisPorProveedor $obtenerAnalisisPorProveedor,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProveedorModel::class);

        $zonaId = $request->user()->acopiador?->zona_id;
        $proveedores = $zonaId !== null ? $this->obtenerProveedores->execute($zonaId) : [];

        $ids = collect($proveedores)->map(fn (Proveedor $p): int => $p->id())->all();

        $resumenEntregas = EntregaModel::whereIn('proveedor_id', $ids)
            ->selectRaw('proveedor_id, count(*) as entregas, max(fecha_hora) as ultima_entrega')
            ->groupBy('proveedor_id')
            ->get()
            ->keyBy('proveedor_id');

        [$fechaDesde, $fechaHasta] = $this->rangoPeriodo($request->string('periodo')->toString() ?: null, $request);
        $atendidosCount = EntregaModel::whereIn('proveedor_id', $ids)
            ->when($fechaDesde !== null && $fechaHasta !== null, fn ($q) => $q->whereBetween('fecha_hora', [$fechaDesde, $fechaHasta]))
            ->distinct('proveedor_id')
            ->count('proveedor_id');

        $filtrados = collect($proveedores)->filter(function (Proveedor $p) use ($request, $resumenEntregas): bool {
            if ($request->filled('estado') && $p->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            if ($request->filled('actividad')) {
                $tieneEntregas = ($resumenEntregas[$p->id()]->entregas ?? 0) > 0;

                if ($request->string('actividad')->toString() === 'con_entregas' && ! $tieneEntregas) {
                    return false;
                }

                if ($request->string('actividad')->toString() === 'sin_entregas' && $tieneEntregas) {
                    return false;
                }
            }

            if ($request->filled('buscar')) {
                $texto = mb_strtolower($request->string('buscar')->toString());
                $coincide = str_contains(mb_strtolower($p->nombres().' '.$p->apellidos()), $texto)
                    || str_contains($p->dni(), $texto);

                if (! $coincide) {
                    return false;
                }
            }

            return true;
        })->values();

        $kpis = [
            'proveedores' => count($proveedores),
            'activos' => collect($proveedores)->filter(fn (Proveedor $p): bool => $p->estado()->value === 'ACTIVO')->count(),
            'inactivos' => collect($proveedores)->filter(fn (Proveedor $p): bool => $p->estado()->value !== 'ACTIVO')->count(),
            'atendidos' => $atendidosCount,
        ];

        return view('acopiador.proveedores.index', [
            'proveedores' => $filtrados->all(),
            'resumenEntregas' => $resumenEntregas,
            'kpis' => $kpis,
        ]);
    }

    public function show(ProveedorModel $proveedor): View
    {
        Gate::authorize('view', $proveedor);

        $entregasRecientes = EntregaModel::where('proveedor_id', $proveedor->id)
            ->orderByDesc('fecha_hora')
            ->limit(5)
            ->get();

        $resumen = [
            'entregasTotales' => EntregaModel::where('proveedor_id', $proveedor->id)->count(),
            'litrosEntregados' => (float) EntregaModel::where('proveedor_id', $proveedor->id)->sum('litros'),
            'ultimaEntrega' => EntregaModel::where('proveedor_id', $proveedor->id)->max('fecha_hora'),
            'pendientesAnalisis' => EntregaModel::where('proveedor_id', $proveedor->id)->where('estado', 'PENDIENTE_ANALISIS')->count(),
        ];

        $ultimoAnalisis = collect($this->obtenerAnalisisPorProveedor->execute($proveedor->id))
            ->sortByDesc(fn ($a) => $a->fechaAnalisis())
            ->first();

        return view('acopiador.proveedores.show', [
            'proveedor' => $this->obtenerProveedor->execute($proveedor->id),
            'entregasRecientes' => $entregasRecientes,
            'resumen' => $resumen,
            'ultimoAnalisis' => $ultimoAnalisis,
        ]);
    }

    /**
     * No expone el qr_token como texto: solo confirma que el proveedor
     * tiene un código QR disponible para identificarse.
     */
    public function qr(ProveedorModel $proveedor): View
    {
        Gate::authorize('view', $proveedor);

        $datosProveedor = $this->obtenerProveedor->execute($proveedor->id);

        return view('acopiador.proveedores.qr', [
            'proveedor' => $datosProveedor,
            'zona' => ZonaModel::find($datosProveedor->zonaId()),
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
