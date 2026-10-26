<?php

namespace App\Http\Controllers\Web\Calidad;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\Contracts\EntregasCalidadListadoQuery;
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
 * NO implementa OCR (los valores ya vienen extraídos en el request). NO
 * decide ACEPTADA/OBSERVADA/RECHAZADA aquí: eso lo calcula
 * CalcularResultadoCalidad dentro de FinalizarAnalisisCalidad. CONFIRMADO
 * sigue siendo inmutable: AnalisisCalidadPolicy::update() lo protege incluso
 * para ADMINISTRADOR (Gate::before tiene una excepción explícita para este
 * caso, ver AppServiceProvider).
 */
final class AnalisisCalidadController extends Controller
{
    /** Tamaños de página que el administrador puede elegir en el listado. */
    private const POR_PAGINA = [25, 50, 100];

    public function __construct(
        private readonly RegistrarAnalisisCalidad $registrarAnalisisCalidad,
        private readonly FinalizarAnalisisCalidad $finalizarAnalisisCalidad,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
        private readonly EntregasCalidadListadoQuery $entregasCalidadListadoQuery,
        private readonly ObtenerParametrosCalidad $obtenerParametrosCalidad,
    ) {}

    /**
     * Listado general de ENTREGAS con su análisis (todas las zonas), paginado en
     * el servidor: las que aún no tienen análisis aparecen como Pendiente.
     * Exclusivo de ADMINISTRADOR, quien entra vía Gate::before.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $filtro = AnalisisCalidadFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            resultado: $request->string('resultado')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            fecha: $request->string('fecha')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
            analistaUserId: $request->filled('analista_id') ? $request->integer('analista_id') : null,
            orden: $request->string('orden')->toString() ?: null,
            direccion: $request->string('dir')->toString() ?: null,
        );

        $porPagina = in_array($request->integer('por_pagina'), self::POR_PAGINA, true) ? $request->integer('por_pagina') : self::POR_PAGINA[0];

        return view('admin.analisis-calidad.index', [
            'datos' => $this->entregasCalidadListadoQuery->paginar($filtro, $porPagina),
            'filtro' => $filtro,
            'porPagina' => $porPagina,
            'opcionesPorPagina' => self::POR_PAGINA,
        ]);
    }

    /**
     * Fragmento (sin layout) del modal "Ver análisis" del listado: datos de la
     * entrega y, si ya tiene análisis, sus parámetros tal como se registraron
     * (analisis_calidad_detalles → parametros_calidad, sin campos fijos).
     */
    public function detalle(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        $entrega->load(['proveedor', 'zona', 'analisisCalidad.detalles.parametro', 'analisisCalidad.confirmadoPor.analistaCalidad']);

        return view('admin.analisis-calidad._detalle', [
            'entrega' => $entrega,
            'analisis' => $entrega->analisisCalidad,
        ]);
    }

    public function show(AnalisisCalidadModel $analisis): View
    {
        Gate::authorize('view', $analisis);

        $analisis->load(['entrega.proveedor', 'entrega.acopiador', 'entrega.zona', 'confirmadoPor', 'detalles.parametro']);
        $sancion = $analisis->sanciones()->latest('fecha')->first();

        return view('admin.analisis-calidad.show', [
            'analisis' => $this->obtenerAnalisisCalidad->porId($analisis->id),
            'registro' => $analisis,
            'sancion' => $sancion,
        ]);
    }

    /**
     * Análisis de una entrega: si todavía no lo tiene (o está en borrador) se
     * registran y confirman aquí los valores de los parámetros de calidad.
     */
    public function porEntrega(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        return view('admin.analisis-calidad.por-entrega', [
            'entrega' => $entrega->load(['proveedor', 'zona', 'acopiador']),
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

        // Traducción HTTP -> Application: el archivo subido se guarda aquí
        // (responsabilidad de la capa de entrega) y se pasa como ruta de
        // texto al Use Case, que nunca conoce Illuminate\Http\UploadedFile.
        // Sin archivo, se usa la foto que ya guardó "Escanear ticket".
        $imagenPath = $request->hasFile('imagen')
            ? $request->file('imagen')->store('analisis-calidad', 'public')
            : ($datos['ticket_path'] ?? null);

        $this->registrarAnalisisCalidad->execute(
            entregaId: (int) $datos['entrega_id'],
            valores: $datos['valores'],
            imagenPath: $imagenPath,
            observacion: $datos['observacion'] ?? null,
            origenes: $datos['origenes'] ?? [],
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
