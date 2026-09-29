<?php

namespace App\Http\Controllers\Web;

use App\Application\Proveedor\ActualizarProveedor;
use App\Application\Proveedor\Contracts\ProveedoresListadoQuery;
use App\Application\Proveedor\Contracts\ProveedorPanelQuery;
use App\Application\Proveedor\DesactivarProveedor;
use App\Application\Proveedor\ProveedoresFiltro;
use App\Application\Proveedor\ReactivarProveedor;
use App\Application\Proveedor\RegistrarProveedor;
use App\Domain\Proveedor\Proveedor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proveedor\ReactivarProveedorRequest;
use App\Http\Requests\Proveedor\StoreProveedorRequest;
use App\Http\Requests\Proveedor\UpdateProveedorRequest;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo Proveedor. No contiene reglas
 * de negocio: valida (Form Request), autoriza (Policy) y delega en el Use
 * Case o la consulta correspondiente.
 *
 * La pantalla es de dos columnas: listado paginado a la izquierda y el
 * panel del proveedor seleccionado a la derecha. El panel se pinta en el
 * servidor en la carga inicial (?proveedor=ID) y, al elegir otra fila, se
 * pide por fetch a panel() sin recargar la página.
 */
final class ProveedorController extends Controller
{
    private const POR_PAGINA = 12;

    public function __construct(
        private readonly RegistrarProveedor $registrarProveedor,
        private readonly ActualizarProveedor $actualizarProveedor,
        private readonly DesactivarProveedor $desactivarProveedor,
        private readonly ReactivarProveedor $reactivarProveedor,
        private readonly ProveedoresListadoQuery $proveedoresListadoQuery,
        private readonly ProveedorPanelQuery $proveedorPanelQuery,
    ) {}

    /**
     * Sin ?proveedor= se selecciona el primero de la página; con él, ese
     * proveedor aunque esté en otra página o fuera del filtro (los enlaces
     * desde Acopio y Zonas llegan así).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProveedorModel::class);

        // Defensa en profundidad: si un ACOPIADOR llegara aquí, queda acotado a su propia zona
        // (nunca a un ?zona_id= del cliente). Su pantalla real es Acopiador\ProveedorController.
        $user = $request->user();
        $zonaId = $user->esAcopiador()
            ? ($user->acopiador?->zona_id ?? 0)
            : ($request->filled('zona_id') ? $request->integer('zona_id') : null);

        $filtro = ProveedoresFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            zonaId: $zonaId,
            seguimiento: $request->string('seguimiento')->toString() ?: null,
        );

        $datos = $this->proveedoresListadoQuery->paginar($filtro, self::POR_PAGINA);
        $seleccionadoId = $request->filled('proveedor')
            ? $request->integer('proveedor')
            : ($datos->filas->first()['id'] ?? null);

        if ($seleccionadoId !== null && Gate::denies('view', ProveedorModel::find($seleccionadoId) ?? new ProveedorModel)) {
            $seleccionadoId = null;
        }

        return view('admin.proveedores.index', [
            'datos' => $datos,
            'panel' => $seleccionadoId !== null ? $this->proveedorPanelQuery->obtener($seleccionadoId) : null,
        ]);
    }

    /** Fragmento HTML del panel derecho (lo pide admin-fichas.js al hacer click en una fila). */
    public function panel(ProveedorModel $proveedor): View
    {
        Gate::authorize('view', $proveedor);

        return view('admin.proveedores._panel', ['panel' => $this->proveedorPanelQuery->obtener($proveedor->id)]);
    }

    /** Ya no hay página de detalle aparte: el proveedor se ve en el panel del listado. */
    public function show(ProveedorModel $proveedor): RedirectResponse
    {
        Gate::authorize('view', $proveedor);

        return redirect()->route('admin.proveedores.index', ['proveedor' => $proveedor->id]);
    }

    public function store(StoreProveedorRequest $request): RedirectResponse
    {
        Gate::authorize('create', ProveedorModel::class);

        $datos = $request->validated();

        $this->registrarProveedor->execute(
            dni: $datos['dni'],
            nombres: $datos['nombres'],
            apellidos: $datos['apellidos'],
            telefono: $datos['telefono'] ?? null,
            username: $datos['username'],
            password: $datos['password'],
            fechaRegistro: isset($datos['fecha_registro'])
                ? new DateTimeImmutable($datos['fecha_registro'])
                : new DateTimeImmutable,
        );

        return redirect()->back()->with('success', 'Proveedor registrado correctamente.');
    }

    public function update(UpdateProveedorRequest $request, ProveedorModel $proveedor): RedirectResponse
    {
        Gate::authorize('update', $proveedor);

        $datos = $request->validated();

        $this->actualizarProveedor->execute(
            proveedorId: $proveedor->id,
            dni: $datos['dni'] ?? null,
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        return redirect()->back()->with('success', 'Proveedor actualizado correctamente.');
    }

    /** Lo usan el switch del listado (JSON) y cualquier formulario clásico (redirección). */
    public function desactivar(Request $request, ProveedorModel $proveedor): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $proveedor);

        return $this->responderCambioDeEstado(
            $request,
            fn (): Proveedor => $this->desactivarProveedor->execute($proveedor->id),
            'Proveedor desactivado correctamente.',
        );
    }

    /** Única vía de reactivación: siempre con la zona (y su ruta) a la que se reincorpora. */
    public function reactivar(ReactivarProveedorRequest $request, ProveedorModel $proveedor): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $proveedor);

        return $this->responderCambioDeEstado(
            $request,
            fn (): Proveedor => $this->reactivarProveedor->execute($proveedor->id, (int) $request->validated('ruta_id')),
            'Proveedor reactivado correctamente.',
        );
    }

    /**
     * Ejecuta el Use Case y responde en JSON (switch del listado) o con
     * redirección. Una regla de negocio incumplida es un 422 con su mensaje,
     * nunca un 500.
     *
     * @param  callable(): Proveedor  $cambio
     */
    private function responderCambioDeEstado(Request $request, callable $cambio, string $mensaje): JsonResponse|RedirectResponse
    {
        try {
            $proveedor = $cambio();
        } catch (DomainException $error) {
            return $request->expectsJson()
                ? response()->json(['message' => $error->getMessage()], 422)
                : redirect()->back()->with('error', $error->getMessage());
        }

        if (! $request->expectsJson()) {
            return redirect()->back()->with('success', $mensaje);
        }

        return response()->json([
            'message' => $mensaje,
            'proveedor' => [
                'id' => $proveedor->id(),
                'estado' => $proveedor->estado()->value,
                'zonaId' => $proveedor->zonaId(),
                'zonaNombre' => $proveedor->zonaId() !== null ? ZonaModel::whereKey($proveedor->zonaId())->value('nombre') : null,
            ],
        ]);
    }
}
