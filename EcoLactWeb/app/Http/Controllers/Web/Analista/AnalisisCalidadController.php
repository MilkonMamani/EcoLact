<?php

namespace App\Http\Controllers\Web\Analista;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\Contracts\AnalisisCalidadListadoQuery;
use App\Application\Calidad\Contracts\MuestreosCalidadQuery;
use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerParametrosCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\FinalizarAnalisisCalidadRequest;
use App\Http\Requests\Calidad\StoreAnalisisCalidadRequest;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use DateTimeImmutable;
use DomainException;
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
 *
 * Calidad trabaja por MUESTREO: "Muestras pendientes" lista solo las
 * entregas seleccionadas como muestra (no todas las entregas sin análisis) y
 * registrar un análisis exige que la entrega sea una muestra pendiente.
 */
final class AnalisisCalidadController extends Controller
{
    public function __construct(
        private readonly RegistrarAnalisisCalidad $registrarAnalisisCalidad,
        private readonly FinalizarAnalisisCalidad $finalizarAnalisisCalidad,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
        private readonly MuestreosCalidadQuery $muestreos,
        private readonly AnalisisCalidadListadoQuery $analisisCalidadListadoQuery,
        private readonly ObtenerParametrosCalidad $obtenerParametrosCalidad,
    ) {}

    /**
     * Dos pestañas server-side: Muestras pendientes (solo las seleccionadas)
     * e Historial (análisis ya registrados), cada una con sus filtros.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $tab = $request->query('tab') === 'historial' ? 'historial' : 'pendientes';

        $muestras = $this->muestreos->pendientes(
            busqueda: $request->string('q')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? (int) $request->query('zona_id') : null,
        );

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
            'muestras' => $muestras,
            'kpisMuestreo' => $this->muestreos->kpis(new DateTimeImmutable),
            'zonas' => $this->muestreos->zonas(),
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
            // null en análisis históricos, anteriores al muestreo.
            'muestra' => $this->muestreos->muestraPorEntrega($analisis->entrega_id),
        ]);
    }

    public function porEntrega(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        return view('calidad.analisis.por-entrega', [
            'entrega' => $entrega->load(['proveedor', 'zona', 'acopiador']),
            'analisis' => $this->obtenerAnalisisCalidad->porEntrega($entrega->id),
            'muestra' => $this->muestreos->muestraPorEntrega($entrega->id),
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

        // Sin archivo, se usa la foto que ya guardó "Escanear ticket".
        $imagenPath = $request->hasFile('imagen')
            ? $request->file('imagen')->store('analisis-calidad', 'public')
            : ($datos['ticket_path'] ?? null);

        try {
            $this->registrarAnalisisCalidad->execute(
                entregaId: (int) $datos['entrega_id'],
                valores: $datos['valores'],
                imagenPath: $imagenPath,
                observacion: $datos['observacion'] ?? null,
                origenes: $datos['origenes'] ?? [],
                exigirMuestra: true,
            );
        } catch (DomainException $excepcion) {
            return redirect()->back()->withInput()->with('error', $excepcion->getMessage());
        }

        return redirect()->back()->with('success', 'Análisis de calidad registrado correctamente.');
    }

    public function finalizar(FinalizarAnalisisCalidadRequest $request, AnalisisCalidadModel $analisis): RedirectResponse
    {
        Gate::authorize('update', $analisis);

        try {
            $this->finalizarAnalisisCalidad->execute($analisis->id, $request->user()->id);
        } catch (DomainException $excepcion) {
            return redirect()->back()->with('error', $excepcion->getMessage());
        }

        return redirect()->back()->with('success', 'Análisis de calidad confirmado correctamente.');
    }
}
