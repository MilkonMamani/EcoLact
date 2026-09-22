<?php

namespace App\Http\Controllers\Web;

use App\Application\Acopio\ObtenerEntregasProveedor;
use App\Application\Calidad\ObtenerAnalisisPorProveedor;
use App\Application\Liquidacion\ObtenerLiquidaciones;
use App\Application\Pago\ObtenerResumenPagosProveedor;
use App\Application\Proveedor\ActualizarProveedor;
use App\Application\Proveedor\Contracts\ProveedoresListadoQuery;
use App\Application\Proveedor\DesactivarProveedor;
use App\Application\Proveedor\ObtenerProveedor;
use App\Application\Proveedor\ObtenerProveedores;
use App\Application\Proveedor\ObtenerZonaHistorialProveedor;
use App\Application\Proveedor\ProveedoresFiltro;
use App\Application\Proveedor\ReactivarProveedor;
use App\Application\Proveedor\RegistrarProveedor;
use App\Application\Ventas\ObtenerVentasProveedor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proveedor\StoreProveedorRequest;
use App\Http\Requests\Proveedor\UpdateProveedorRequest;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo Proveedor. No contiene reglas
 * de negocio: valida (Form Request), autoriza (Policy) y delega en el Use
 * Case correspondiente.
 */
final class ProveedorController extends Controller
{
    public function __construct(
        private readonly RegistrarProveedor $registrarProveedor,
        private readonly ActualizarProveedor $actualizarProveedor,
        private readonly DesactivarProveedor $desactivarProveedor,
        private readonly ReactivarProveedor $reactivarProveedor,
        private readonly ObtenerProveedor $obtenerProveedor,
        private readonly ObtenerProveedores $obtenerProveedores,
        private readonly ProveedoresListadoQuery $proveedoresListadoQuery,
        private readonly ObtenerEntregasProveedor $obtenerEntregasProveedor,
        private readonly ObtenerAnalisisPorProveedor $obtenerAnalisisPorProveedor,
        private readonly ObtenerLiquidaciones $obtenerLiquidaciones,
        private readonly ObtenerVentasProveedor $obtenerVentasProveedor,
        private readonly ObtenerZonaHistorialProveedor $obtenerZonaHistorialProveedor,
        private readonly ObtenerResumenPagosProveedor $obtenerResumenPagosProveedor,
    ) {}

    /**
     * ProveedorPolicy::viewAny solo admite ACOPIADOR (ADMINISTRADOR entra
     * vía Gate::before); nunca PROVEEDOR: un proveedor no necesita listar
     * "proveedores", solo consulta su propio registro (show/update). El
     * ACOPIADOR SIEMPRE queda acotado a su propia zona (nunca a un
     * ?zona_id= arbitrario del cliente); el ADMINISTRADOR ve todas las
     * zonas salvo que filtre explícitamente por una, con KPIs/búsqueda.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProveedorModel::class);

        $user = $request->user();

        if ($user->esAcopiador()) {
            $zonaId = $user->acopiador?->zona_id;
            $proveedores = $zonaId !== null ? $this->obtenerProveedores->execute($zonaId) : [];

            return view('admin.proveedores.index', ['proveedores' => $proveedores]);
        }

        $filtro = ProveedoresFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            seguimiento: $request->string('seguimiento')->toString() ?: null,
        );

        return view('admin.proveedores.index', ['datos' => $this->proveedoresListadoQuery->obtener($filtro)]);
    }

    public function show(ProveedorModel $proveedor): View
    {
        Gate::authorize('view', $proveedor);

        $datosProveedor = $this->obtenerProveedor->execute($proveedor->id);
        $rutaDetalle = RutaDetalleModel::with('ruta.zona')->where('proveedor_id', $proveedor->id)->first();

        return view('admin.proveedores.show', [
            'proveedor' => $datosProveedor,
            'zona' => ZonaModel::find($datosProveedor->zonaId()),
            'rutaActual' => $rutaDetalle?->ruta,
            'asignacion' => $rutaDetalle,
            'entregas' => $this->obtenerEntregasProveedor->execute($proveedor->id),
            'analisis' => $this->obtenerAnalisisPorProveedor->execute($proveedor->id),
            'liquidaciones' => $this->obtenerLiquidaciones->execute($proveedor->id),
            'resumenPagos' => $this->obtenerResumenPagosProveedor->execute($proveedor->id),
            'compras' => $this->obtenerVentasProveedor->execute($proveedor->id),
            'zonaHistorial' => $this->obtenerZonaHistorialProveedor->execute($proveedor->id),
        ]);
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

    public function desactivar(ProveedorModel $proveedor): RedirectResponse
    {
        Gate::authorize('update', $proveedor);

        $this->desactivarProveedor->execute($proveedor->id);

        return redirect()->back()->with('success', 'Proveedor desactivado correctamente.');
    }

    public function reactivar(ProveedorModel $proveedor): RedirectResponse
    {
        Gate::authorize('update', $proveedor);

        $this->reactivarProveedor->execute($proveedor->id);

        return redirect()->back()->with('success', 'Proveedor reactivado correctamente.');
    }
}
