<?php

namespace App\Http\Controllers\Web\Analista;

use App\Application\Acopio\Contracts\EntregasListadoQuery;
use App\Application\Acopio\EntregasFiltro;
use App\Application\Acopio\ObtenerEntrega;
use App\Application\Calidad\RegistrarEntregaMuestreada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\StoreEntregaRequest;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del rol CALIDAD: de CUALQUIER zona (nunca acotado a una zona
 * propia como el ACOPIADOR), para poder revisar el acopio del día y elegir
 * a qué proveedor le hace el control de calidad. Solo lectura, salvo
 * `store`: registrar una muestra que el analista toma directamente del
 * proveedor (antes de que pase por el acopiador) — ver
 * RegistrarEntregaMuestreada.
 */
final class EntregaController extends Controller
{
    public function __construct(
        private readonly EntregasListadoQuery $entregasListadoQuery,
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly RegistrarEntregaMuestreada $registrarEntregaMuestreada,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EntregaModel::class);

        $filtro = EntregasFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? (int) $request->query('zona_id') : null,
            acopiadorId: $request->filled('acopiador_id') ? (int) $request->query('acopiador_id') : null,
            estado: $request->string('estado')->toString() ?: null,
            fecha: $request->string('fecha')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
        );

        return view('calidad.entregas.index', [
            'datos' => $this->entregasListadoQuery->obtener($filtro),
        ]);
    }

    public function show(EntregaModel $entrega): View
    {
        Gate::authorize('view', $entrega);

        $entrega->load(['proveedor', 'zona', 'acopiador']);

        return view('calidad.entregas.show', [
            'entrega' => $this->obtenerEntrega->execute($entrega->id),
            'registro' => $entrega,
        ]);
    }

    public function store(StoreEntregaRequest $request): RedirectResponse
    {
        Gate::authorize('create', EntregaModel::class);

        $datos = $request->validated();

        $entrega = $this->registrarEntregaMuestreada->execute(
            metodoIdentificacion: $datos['metodo_identificacion'],
            identificador: $datos['identificador'],
            analistaUserId: $request->user()->id,
            litros: (float) $datos['litros'],
            observacion: $datos['observacion'] ?? null,
        );

        return redirect()->route('calidad.entregas.analisis.index', $entrega->id())->with('success', 'Muestra registrada. Continúa con el análisis.');
    }
}
