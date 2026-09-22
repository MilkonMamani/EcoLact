<?php

namespace App\Http\Controllers\Web\Analista;

use App\Application\Acopio\ObtenerEntregasProveedor;
use App\Application\Calidad\ObtenerAnalisisPorProveedor;
use App\Application\Proveedor\Contracts\ProveedoresListadoQuery;
use App\Application\Proveedor\ObtenerProveedor;
use App\Application\Proveedor\ObtenerZonaHistorialProveedor;
use App\Application\Proveedor\ProveedoresFiltro;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del rol CALIDAD: solo lectura, de CUALQUIER zona. Sirve para
 * elegir destinatarios de comunicados (propuestas de capacitación) y
 * revisar el historial de entregas/calidad de un proveedor antes de
 * escribirle. NO expone Liquidaciones ni Compras (eso es alcance
 * financiero, ajeno a Calidad).
 */
final class ProveedorController extends Controller
{
    public function __construct(
        private readonly ObtenerProveedor $obtenerProveedor,
        private readonly ProveedoresListadoQuery $proveedoresListadoQuery,
        private readonly ObtenerEntregasProveedor $obtenerEntregasProveedor,
        private readonly ObtenerAnalisisPorProveedor $obtenerAnalisisPorProveedor,
        private readonly ObtenerZonaHistorialProveedor $obtenerZonaHistorialProveedor,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProveedorModel::class);

        $filtro = ProveedoresFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            seguimiento: $request->string('seguimiento')->toString() ?: null,
        );

        return view('calidad.proveedores.index', [
            'datos' => $this->proveedoresListadoQuery->obtener($filtro),
        ]);
    }

    public function show(ProveedorModel $proveedor): View
    {
        Gate::authorize('view', $proveedor);

        return view('calidad.proveedores.show', [
            'proveedor' => $this->obtenerProveedor->execute($proveedor->id),
            'entregas' => $this->obtenerEntregasProveedor->execute($proveedor->id),
            'analisis' => $this->obtenerAnalisisPorProveedor->execute($proveedor->id),
            'zonaHistorial' => $this->obtenerZonaHistorialProveedor->execute($proveedor->id),
        ]);
    }
}
