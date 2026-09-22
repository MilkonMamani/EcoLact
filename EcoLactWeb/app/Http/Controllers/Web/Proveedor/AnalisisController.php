<?php

namespace App\Http\Controllers\Web\Proveedor;

use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisProveedor;
use App\Domain\Calidad\AnalisisCalidad;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Autoservicio del PROVEEDOR: "Mi calidad" (antes "mis análisis"). Nunca
 * acepta un proveedor_id externo: ObtenerAnalisisProveedor resuelve el
 * proveedor propio a partir del usuario autenticado.
 */
final class AnalisisController extends Controller
{
    public function __construct(
        private readonly ObtenerAnalisisProveedor $obtenerAnalisisProveedor,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
    ) {}

    public function index(Request $request): View
    {
        $analisis = $this->obtenerAnalisisProveedor->execute($request->user()->id);

        $hoy = CarbonImmutable::now(config('app.timezone'));
        $desde = match ($request->string('periodo')->toString() ?: null) {
            'ultimos_30_dias' => $hoy->subDays(30),
            'este_mes' => $hoy->startOfMonth(),
            default => null,
        };

        $filtrados = collect($analisis)->filter(function (AnalisisCalidad $item) use ($request, $desde): bool {
            if ($desde !== null && $item->fechaAnalisis() < $desde) {
                return false;
            }

            if ($request->filled('resultado') && $item->resultado()?->value !== $request->string('resultado')->toString()) {
                return false;
            }

            if ($request->filled('estado') && $item->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            return true;
        })->sortByDesc(fn (AnalisisCalidad $item) => $item->fechaAnalisis())->values();

        $kpis = [
            'total' => $filtrados->count(),
            'aceptados' => $filtrados->filter(fn (AnalisisCalidad $a): bool => $a->resultado()?->value === 'ACEPTADA')->count(),
            'observados' => $filtrados->filter(fn (AnalisisCalidad $a): bool => $a->resultado()?->value === 'OBSERVADA')->count(),
            'rechazados' => $filtrados->filter(fn (AnalisisCalidad $a): bool => $a->resultado()?->value === 'RECHAZADA')->count(),
        ];

        return view('proveedor.analisis.index', [
            'analisis' => $filtrados->all(),
            'kpis' => $kpis,
        ]);
    }

    public function show(AnalisisCalidadModel $analisis): View
    {
        Gate::authorize('view', $analisis);

        $analisis->load(['entrega.acopiador', 'entrega.zona', 'detalles.parametro']);
        $sancion = $analisis->sanciones()->latest('fecha')->first();

        return view('proveedor.analisis.show', [
            'analisis' => $this->obtenerAnalisisCalidad->porId($analisis->id),
            'registro' => $analisis,
            'sancion' => $sancion,
        ]);
    }
}
