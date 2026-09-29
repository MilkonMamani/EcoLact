<?php

namespace App\Http\Controllers\Web;

use App\Application\PersonalVentas\ActualizarPersonalVentas;
use App\Application\PersonalVentas\DesactivarPersonalVentas;
use App\Application\PersonalVentas\ReactivarPersonalVentas;
use App\Application\PersonalVentas\RegistrarPersonalVentas;
use App\Application\Trabajador\TipoFichaPersonal;
use App\Domain\PersonalVentas\PersonalVentas;
use App\Http\Controllers\Controller;
use App\Http\Requests\PersonalVentas\StorePersonalVentasRequest;
use App\Http\Requests\PersonalVentas\UpdatePersonalVentasRequest;
use App\Http\Support\PantallaFichasPersonal;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo PersonalVentas. Exclusivo de
 * ADMINISTRADOR (Gate::before + PersonalVentasPolicy): sin zona.
 *
 * La pantalla es la misma de Acopiadores (PantallaFichasPersonal): listado
 * paginado a la izquierda y panel del seleccionado a la derecha, pintado en
 * la carga inicial (?personal=ID) y pedido por fetch a panel() al elegir otra
 * fila. El switch de estado llama a desactivar/reactivar pidiendo JSON; sin
 * zona, reactivar no pide nada más.
 */
final class PersonalVentasController extends Controller
{
    public function __construct(
        private readonly RegistrarPersonalVentas $registrarPersonalVentas,
        private readonly ActualizarPersonalVentas $actualizarPersonalVentas,
        private readonly DesactivarPersonalVentas $desactivarPersonalVentas,
        private readonly ReactivarPersonalVentas $reactivarPersonalVentas,
        private readonly PantallaFichasPersonal $pantalla,
    ) {}

    /** Sin ?personal= se selecciona el primero de la página. */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PersonalVentasModel::class);

        return $this->pantalla->index(TipoFichaPersonal::PERSONAL_VENTAS, $request);
    }

    /** Fragmento HTML del panel derecho (lo pide admin-fichas.js al elegir una fila). */
    public function panel(Request $request, PersonalVentasModel $personalVenta): View
    {
        Gate::authorize('view', $personalVenta);

        return $this->pantalla->panel(TipoFichaPersonal::PERSONAL_VENTAS, $personalVenta->id, $request);
    }

    /** Ya no hay página de detalle aparte: se ve en el panel del listado. */
    public function show(PersonalVentasModel $personalVenta): RedirectResponse
    {
        Gate::authorize('view', $personalVenta);

        return redirect()->route('admin.personal-ventas.index', ['personal' => $personalVenta->id]);
    }

    public function store(StorePersonalVentasRequest $request): RedirectResponse
    {
        Gate::authorize('create', PersonalVentasModel::class);

        $datos = $request->validated();

        $this->registrarPersonalVentas->execute(
            dni: $datos['dni'],
            nombres: $datos['nombres'],
            apellidos: $datos['apellidos'],
            telefono: $datos['telefono'] ?? null,
            username: $datos['username'],
            password: $datos['password'],
        );

        return redirect()->back()->with('success', 'Personal de ventas registrado correctamente.');
    }

    public function update(UpdatePersonalVentasRequest $request, PersonalVentasModel $personalVenta): RedirectResponse
    {
        Gate::authorize('update', $personalVenta);

        $datos = $request->validated();

        $this->actualizarPersonalVentas->execute(
            personalId: $personalVenta->id,
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        return redirect()->back()->with('success', 'Personal de ventas actualizado correctamente.');
    }

    public function desactivar(Request $request, PersonalVentasModel $personalVenta): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $personalVenta);

        return $this->pantalla->responderCambioDeEstado(
            $request,
            fn (): PersonalVentas => $this->desactivarPersonalVentas->execute($personalVenta->id),
            'Personal de ventas desactivado correctamente.',
        );
    }

    public function reactivar(Request $request, PersonalVentasModel $personalVenta): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $personalVenta);

        return $this->pantalla->responderCambioDeEstado(
            $request,
            fn (): PersonalVentas => $this->reactivarPersonalVentas->execute($personalVenta->id),
            'Personal de ventas reactivado correctamente.',
        );
    }
}
