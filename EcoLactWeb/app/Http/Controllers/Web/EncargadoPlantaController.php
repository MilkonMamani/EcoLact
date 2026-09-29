<?php

namespace App\Http\Controllers\Web;

use App\Application\EncargadoPlanta\ActualizarEncargadoPlanta;
use App\Application\EncargadoPlanta\DesactivarEncargadoPlanta;
use App\Application\EncargadoPlanta\ReactivarEncargadoPlanta;
use App\Application\EncargadoPlanta\RegistrarEncargadoPlanta;
use App\Application\Trabajador\TipoFichaPersonal;
use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Http\Controllers\Controller;
use App\Http\Requests\EncargadoPlanta\StoreEncargadoPlantaRequest;
use App\Http\Requests\EncargadoPlanta\UpdateEncargadoPlantaRequest;
use App\Http\Support\PantallaFichasPersonal;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo EncargadoPlanta. Exclusivo de
 * ADMINISTRADOR (Gate::before + EncargadoPlantaPolicy): sin zona, porque el
 * encargado de planta opera sobre toda la planta.
 *
 * La pantalla es la misma de Acopiadores (PantallaFichasPersonal): listado
 * paginado a la izquierda y panel del seleccionado a la derecha, pintado en
 * la carga inicial (?encargado=ID) y pedido por fetch a panel() al elegir otra
 * fila. El switch de estado llama a desactivar/reactivar pidiendo JSON; sin
 * zona, reactivar no pide nada más.
 */
final class EncargadoPlantaController extends Controller
{
    public function __construct(
        private readonly RegistrarEncargadoPlanta $registrarEncargadoPlanta,
        private readonly ActualizarEncargadoPlanta $actualizarEncargadoPlanta,
        private readonly DesactivarEncargadoPlanta $desactivarEncargadoPlanta,
        private readonly ReactivarEncargadoPlanta $reactivarEncargadoPlanta,
        private readonly PantallaFichasPersonal $pantalla,
    ) {}

    /** Sin ?encargado= se selecciona el primero de la página. */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EncargadoPlantaModel::class);

        return $this->pantalla->index(TipoFichaPersonal::ENCARGADO_PLANTA, $request);
    }

    /** Fragmento HTML del panel derecho (lo pide admin-fichas.js al elegir una fila). */
    public function panel(Request $request, EncargadoPlantaModel $encargado): View
    {
        Gate::authorize('view', $encargado);

        return $this->pantalla->panel(TipoFichaPersonal::ENCARGADO_PLANTA, $encargado->id, $request);
    }

    /** Ya no hay página de detalle aparte: se ve en el panel del listado. */
    public function show(EncargadoPlantaModel $encargado): RedirectResponse
    {
        Gate::authorize('view', $encargado);

        return redirect()->route('admin.encargados-planta.index', ['encargado' => $encargado->id]);
    }

    public function store(StoreEncargadoPlantaRequest $request): RedirectResponse
    {
        Gate::authorize('create', EncargadoPlantaModel::class);

        $datos = $request->validated();

        $this->registrarEncargadoPlanta->execute(
            dni: $datos['dni'],
            nombres: $datos['nombres'],
            apellidos: $datos['apellidos'],
            telefono: $datos['telefono'] ?? null,
            username: $datos['username'],
            password: $datos['password'],
        );

        return redirect()->back()->with('success', 'Encargado de planta registrado correctamente.');
    }

    public function update(UpdateEncargadoPlantaRequest $request, EncargadoPlantaModel $encargado): RedirectResponse
    {
        Gate::authorize('update', $encargado);

        $datos = $request->validated();

        $this->actualizarEncargadoPlanta->execute(
            encargadoId: $encargado->id,
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        return redirect()->back()->with('success', 'Encargado de planta actualizado correctamente.');
    }

    public function desactivar(Request $request, EncargadoPlantaModel $encargado): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $encargado);

        return $this->pantalla->responderCambioDeEstado(
            $request,
            fn (): EncargadoPlanta => $this->desactivarEncargadoPlanta->execute($encargado->id),
            'Encargado de planta desactivado correctamente.',
        );
    }

    public function reactivar(Request $request, EncargadoPlantaModel $encargado): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $encargado);

        return $this->pantalla->responderCambioDeEstado(
            $request,
            fn (): EncargadoPlanta => $this->reactivarEncargadoPlanta->execute($encargado->id),
            'Encargado de planta reactivado correctamente.',
        );
    }
}
