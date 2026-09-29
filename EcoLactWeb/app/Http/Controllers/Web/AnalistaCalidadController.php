<?php

namespace App\Http\Controllers\Web;

use App\Application\AnalistaCalidad\ActualizarAnalistaCalidad;
use App\Application\AnalistaCalidad\DesactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\ReactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\RegistrarAnalistaCalidad;
use App\Application\Trabajador\TipoFichaPersonal;
use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalistaCalidad\StoreAnalistaCalidadRequest;
use App\Http\Requests\AnalistaCalidad\UpdateAnalistaCalidadRequest;
use App\Http\Support\PantallaFichasPersonal;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo AnalistaCalidad. Exclusivo de
 * ADMINISTRADOR (Gate::before + AnalistaCalidadPolicy): sin zona, el
 * analista controla calidad en cualquier zona.
 *
 * La pantalla es la misma de Acopiadores (PantallaFichasPersonal): listado
 * paginado a la izquierda y panel del seleccionado a la derecha, pintado en
 * la carga inicial (?analista=ID) y pedido por fetch a panel() al elegir otra
 * fila. El switch de estado llama a desactivar/reactivar pidiendo JSON; sin
 * zona, reactivar no pide nada más.
 */
final class AnalistaCalidadController extends Controller
{
    public function __construct(
        private readonly RegistrarAnalistaCalidad $registrarAnalistaCalidad,
        private readonly ActualizarAnalistaCalidad $actualizarAnalistaCalidad,
        private readonly DesactivarAnalistaCalidad $desactivarAnalistaCalidad,
        private readonly ReactivarAnalistaCalidad $reactivarAnalistaCalidad,
        private readonly PantallaFichasPersonal $pantalla,
    ) {}

    /** Sin ?analista= se selecciona el primero de la página. */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AnalistaCalidadModel::class);

        return $this->pantalla->index(TipoFichaPersonal::ANALISTA_CALIDAD, $request);
    }

    /** Fragmento HTML del panel derecho (lo pide admin-fichas.js al elegir una fila). */
    public function panel(Request $request, AnalistaCalidadModel $analista): View
    {
        Gate::authorize('view', $analista);

        return $this->pantalla->panel(TipoFichaPersonal::ANALISTA_CALIDAD, $analista->id, $request);
    }

    /** Ya no hay página de detalle aparte: se ve en el panel del listado. */
    public function show(AnalistaCalidadModel $analista): RedirectResponse
    {
        Gate::authorize('view', $analista);

        return redirect()->route('admin.analistas-calidad.index', ['analista' => $analista->id]);
    }

    public function store(StoreAnalistaCalidadRequest $request): RedirectResponse
    {
        Gate::authorize('create', AnalistaCalidadModel::class);

        $datos = $request->validated();

        $this->registrarAnalistaCalidad->execute(
            dni: $datos['dni'],
            nombres: $datos['nombres'],
            apellidos: $datos['apellidos'],
            telefono: $datos['telefono'] ?? null,
            username: $datos['username'],
            password: $datos['password'],
        );

        return redirect()->back()->with('success', 'Analista de calidad registrado correctamente.');
    }

    public function update(UpdateAnalistaCalidadRequest $request, AnalistaCalidadModel $analista): RedirectResponse
    {
        Gate::authorize('update', $analista);

        $datos = $request->validated();

        $this->actualizarAnalistaCalidad->execute(
            analistaId: $analista->id,
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        return redirect()->back()->with('success', 'Analista de calidad actualizado correctamente.');
    }

    public function desactivar(Request $request, AnalistaCalidadModel $analista): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $analista);

        return $this->pantalla->responderCambioDeEstado(
            $request,
            fn (): AnalistaCalidad => $this->desactivarAnalistaCalidad->execute($analista->id),
            'Analista de calidad desactivado correctamente.',
        );
    }

    public function reactivar(Request $request, AnalistaCalidadModel $analista): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $analista);

        return $this->pantalla->responderCambioDeEstado(
            $request,
            fn (): AnalistaCalidad => $this->reactivarAnalistaCalidad->execute($analista->id),
            'Analista de calidad reactivado correctamente.',
        );
    }
}
