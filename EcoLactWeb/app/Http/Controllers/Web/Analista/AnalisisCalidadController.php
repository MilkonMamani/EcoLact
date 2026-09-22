<?php

namespace App\Http\Controllers\Web\Analista;

use App\Application\Acopio\Contracts\EntregasListadoQuery;
use App\Application\Acopio\EntregasFiltro;
use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\Contracts\AnalisisCalidadListadoQuery;
use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerParametrosCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\FinalizarAnalisisCalidadRequest;
use App\Http\Requests\Calidad\StoreAnalisisCalidadRequest;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del rol CALIDAD: es quien realiza el control de calidad técnico
 * (reutiliza los mismos Use Cases que Web\Calidad\AnalisisCalidadController
 * admin), sin estar acotado a una zona propia — un analista puede analizar
 * entregas de cualquier zona. NO implementa OCR ni decide
 * ACEPTADA/OBSERVADA/RECHAZADA aquí (lo calcula CalcularResultadoCalidad
 * dentro de FinalizarAnalisisCalidad, que además dispara la advertencia de
 * calidad correspondiente). Un análisis CONFIRMADO es inmutable, incluso
 * para el analista que lo confirmó.
 */
final class AnalisisCalidadController extends Controller
{
    public function __construct(
        private readonly RegistrarAnalisisCalidad $registrarAnalisisCalidad,
        private readonly FinalizarAnalisisCalidad $finalizarAnalisisCalidad,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
        private readonly EntregasListadoQuery $entregasListadoQuery,
        private readonly AnalisisCalidadListadoQuery $analisisCalidadListadoQuery,
        private readonly ObtenerParametrosCalidad $obtenerParametrosCalidad,
    ) {}

    /**
     * Dos pestañas server-side (no cliente): Pendientes e Historial, cada
     * una con su propio conjunto de filtros porque acotan datos distintos
     * (entregas sin análisis vs. análisis ya registrados).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $tab = $request->query('tab') === 'historial' ? 'historial' : 'pendientes';

        $pendientes = $this->entregasListadoQuery->obtener(EntregasFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? (int) $request->query('zona_id') : null,
            acopiadorId: null,
            estado: 'PENDIENTE_ANALISIS',
            fecha: $request->string('fecha')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
        ));

        $historial = $this->analisisCalidadListadoQuery->obtener(AnalisisCalidadFiltro::resolver(
            busqueda: $request->string('q2')->toString() ?: null,
            zonaId: $request->filled('zona_id2') ? (int) $request->query('zona_id2') : null,
            resultado: $request->string('resultado')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            fecha: $request->string('fecha2')->toString() ?: null,
            desde: $request->string('desde2')->toString() ?: null,
            hasta: $request->string('hasta2')->toString() ?: null,
        ));

        return view('calidad.analisis-calidad.index', [
            'tab' => $tab,
            'pendientes' => $pendientes,
            'historial' => $historial,
        ]);
    }

    public function show(AnalisisCalidadModel $analisis): View
    {
        Gate::authorize('view', $analisis);

        $analisis->load(['entrega.proveedor', 'entrega.acopiador', 'entrega.zona', 'confirmadoPor', 'detalles.parametro']);

        return view('calidad.analisis-calidad.show', [
            'analisis' => $this->obtenerAnalisisCalidad->porId($analisis->id),
            'registro' => $analisis,
        ]);
    }

    public function porEntrega(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        return view('calidad.analisis.por-entrega', [
            'entrega' => $entrega,
            'analisis' => $this->obtenerAnalisisCalidad->porEntrega($entrega->id),
            'parametros' => array_values(array_filter(
                $this->obtenerParametrosCalidad->execute(),
                static fn ($parametro): bool => $parametro->estaActivo(),
            )),
        ]);
    }

    public function store(StoreAnalisisCalidadRequest $request): RedirectResponse
    {
        Gate::authorize('create', AnalisisCalidadModel::class);

        $datos = $request->validated();

        $imagenPath = $request->hasFile('imagen')
            ? $request->file('imagen')->store('analisis-calidad', 'public')
            : null;

        $this->registrarAnalisisCalidad->execute(
            entregaId: (int) $datos['entrega_id'],
            valores: $datos['valores'],
            imagenPath: $imagenPath,
            observacion: $datos['observacion'] ?? null,
        );

        return redirect()->back()->with('success', 'Análisis de calidad registrado correctamente.');
    }

    public function finalizar(FinalizarAnalisisCalidadRequest $request, AnalisisCalidadModel $analisis): RedirectResponse
    {
        Gate::authorize('update', $analisis);

        $this->finalizarAnalisisCalidad->execute($analisis->id, $request->user()->id);

        return redirect()->back()->with('success', 'Análisis de calidad confirmado correctamente.');
    }
}
