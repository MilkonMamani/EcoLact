<?php

namespace App\Http\Controllers\Web;

use App\Application\Acopiador\ObtenerAcopiadores;
use App\Application\Recorrido\AbrirRecorrido;
use App\Application\Recorrido\ObtenerRecorridosDeRuta;
use App\Application\Ruta\ActualizarRuta;
use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\Contracts\RutaDetallesListadoQuery;
use App\Application\Ruta\ObtenerRuta;
use App\Application\Ruta\QuitarProveedorDeRuta;
use App\Application\Ruta\RegistrarRuta;
use App\Application\Ruta\ReordenarRutaDetalles;
use App\Application\Ruta\RutaDetalleFila;
use App\Application\Shared\CampoActualizable;
use App\Domain\Ruta\Ruta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recorrido\AbrirRecorridoRequest;
use App\Http\Requests\Ruta\AgregarProveedorARutaRequest;
use App\Http\Requests\Ruta\QuitarProveedorDeRutaRequest;
use App\Http\Requests\Ruta\ReordenarRutaDetallesRequest;
use App\Http\Requests\Ruta\StoreRutaRequest;
use App\Http\Requests\Ruta\UpdateRutaRequest;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Administración de Rutas dentro de una Zona: crear/editar la ruta,
 * gestionar la lista ("carrito") de proveedores asignados y su orden de
 * visita, y asignar el acopiador que abrirá el recorrido operativo. Toda la
 * autorización pasa por Gate::before de ADMINISTRADOR (RutaPolicy siempre
 * devuelve false: ver su docblock).
 */
final class RutaController extends Controller
{
    public function __construct(
        private readonly RegistrarRuta $registrarRuta,
        private readonly ActualizarRuta $actualizarRuta,
        private readonly ObtenerRuta $obtenerRuta,
        private readonly RutaDetallesListadoQuery $rutaDetallesListadoQuery,
        private readonly AsignarProveedorARuta $asignarProveedorARuta,
        private readonly QuitarProveedorDeRuta $quitarProveedorDeRuta,
        private readonly ReordenarRutaDetalles $reordenarRutaDetalles,
        private readonly ObtenerAcopiadores $obtenerAcopiadores,
        private readonly AbrirRecorrido $abrirRecorrido,
        private readonly ObtenerRecorridosDeRuta $obtenerRecorridosDeRuta,
    ) {}

    /**
     * Además del alta tradicional (POST + redirect, usada por /admin/zonas/{zona}),
     * responde JSON cuando se pide: el modal "Asignar proveedor a zona y ruta" de
     * /admin/zonas la usa para crear al vuelo la "Ruta principal" de una zona que
     * todavía no tiene ninguna, sin salir del modal.
     */
    public function store(StoreRutaRequest $request, ZonaModel $zona): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', RutaModel::class);

        $datos = $request->validated();

        try {
            $ruta = $this->registrarRuta->execute(
                zonaId: $zona->id,
                nombre: $datos['nombre'],
                descripcion: $datos['descripcion'] ?? null,
                estado: $datos['estado'],
            );
        } catch (DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['nombre' => [$e->getMessage()]],
                ], 422);
            }

            return redirect()->back()->withErrors(['nombre' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'ruta' => ['id' => $ruta->id(), 'nombre' => $ruta->nombre()]]);
        }

        return redirect()->back()
            ->with('success', 'Ruta creada correctamente.')
            ->with('zonaAbierta', $zona->id)
            ->with('rutaAbierta', $ruta->id());
    }

    public function show(RutaModel $ruta): View
    {
        Gate::authorize('view', $ruta);

        return view('admin.rutas.show', $this->datosPanel($ruta));
    }

    /**
     * Fragmento HTML del panel de la ruta (mapa, búsqueda/alta de
     * proveedores, orden de visita y recorridos), sin el layout de admin.
     * Lo consume el acordeón de /admin/zonas para cargar cada ruta bajo
     * demanda, la primera vez que el administrador la despliega, sin
     * navegar a una página aparte.
     */
    public function panel(RutaModel $ruta): View
    {
        Gate::authorize('view', $ruta);

        return view('admin.rutas._panel', $this->datosPanel($ruta));
    }

    /**
     * @return array{ruta: Ruta, zona: ZonaModel, detalles: array<int, RutaDetalleFila>, acopiadoresDeLaZona: array, recorridos: array}
     */
    private function datosPanel(RutaModel $ruta): array
    {
        return [
            'ruta' => $this->obtenerRuta->execute($ruta->id),
            'zona' => $ruta->zona,
            'detalles' => $this->rutaDetallesListadoQuery->obtener($ruta->id),
            'acopiadoresDeLaZona' => $this->obtenerAcopiadores->execute($ruta->zona_id),
            'recorridos' => $this->obtenerRecorridosDeRuta->execute($ruta->id),
        ];
    }

    public function update(UpdateRutaRequest $request, RutaModel $ruta): RedirectResponse
    {
        Gate::authorize('update', $ruta);

        $datos = $request->validated();

        try {
            $this->actualizarRuta->execute(
                rutaId: $ruta->id,
                nombre: $datos['nombre'] ?? null,
                descripcion: array_key_exists('descripcion', $datos) ? CampoActualizable::conValor($datos['descripcion']) : null,
                estado: $datos['estado'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['nombre' => $e->getMessage()]);
        }

        return redirect()->back()
            ->with('success', 'Ruta actualizada correctamente.')
            ->with('zonaAbierta', $ruta->zona_id)
            ->with('rutaAbierta', $ruta->id);
    }

    /**
     * Búsqueda de proveedores por nombre o DNI para agregarlos a la ruta
     * (lista dinámica tipo "carrito"). Anota la ruta/zona actual de cada
     * proveedor, si ya tiene una, para que el administrador vea de un
     * vistazo si agregarlo implica un traslado.
     */
    public function buscarProveedores(Request $request, RutaModel $ruta): JsonResponse
    {
        Gate::authorize('view', $ruta);

        $q = trim((string) $request->string('q'));

        if ($q === '') {
            return response()->json([]);
        }

        $proveedores = ProveedorModel::query()
            ->where('estado', 'ACTIVO')
            ->where(function ($query) use ($q) {
                $query->where('dni', 'like', "%{$q}%")
                    ->orWhere('nombres', 'like', "%{$q}%")
                    ->orWhere('apellidos', 'like', "%{$q}%");
            })
            ->with('rutaDetalle.ruta.zona')
            ->orderBy('nombres')
            ->limit(20)
            ->get();

        return response()->json($proveedores->map(fn (ProveedorModel $p): array => [
            'id' => $p->id,
            'nombre' => trim($p->nombres.' '.$p->apellidos),
            'dni' => $p->dni,
            'yaEnEstaRuta' => $p->rutaDetalle?->ruta_id === $ruta->id,
            'rutaActual' => $p->rutaDetalle
                ? "{$p->rutaDetalle->ruta->zona->nombre} · {$p->rutaDetalle->ruta->nombre}"
                : null,
        ])->values());
    }

    public function agregarProveedor(AgregarProveedorARutaRequest $request, RutaModel $ruta): RedirectResponse
    {
        Gate::authorize('update', $ruta);

        $datos = $request->validated();

        try {
            $this->asignarProveedorARuta->execute(
                rutaId: $ruta->id,
                proveedorId: (int) $datos['proveedor_id'],
                ordenVisita: isset($datos['orden_visita']) ? (int) $datos['orden_visita'] : null,
                motivo: $datos['motivo'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['proveedor_id' => $e->getMessage()]);
        }

        return redirect()->back()
            ->with('success', 'Proveedor agregado a la ruta.')
            ->with('zonaAbierta', $ruta->zona_id)
            ->with('rutaAbierta', $ruta->id);
    }

    public function quitarProveedor(QuitarProveedorDeRutaRequest $request, RutaModel $ruta, RutaDetalleModel $detalle): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $ruta);

        if ($detalle->ruta_id !== $ruta->id) {
            abort(404);
        }

        try {
            $this->quitarProveedorDeRuta->execute(
                proveedorId: $detalle->proveedor_id,
                motivo: $request->validated('motivo'),
            );
        } catch (DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['proveedor_id' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()
            ->with('success', 'Proveedor retirado de la ruta.')
            ->with('zonaAbierta', $ruta->zona_id)
            ->with('rutaAbierta', $ruta->id);
    }

    public function reordenarDetalles(ReordenarRutaDetallesRequest $request, RutaModel $ruta): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $ruta);

        try {
            $this->reordenarRutaDetalles->execute($ruta->id, $request->validated('orden'));
        } catch (DomainException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['orden' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Orden de visita actualizado.');
    }

    /**
     * Asigna un acopiador a la ruta y abre (o reutiliza, si ya existe) el
     * recorrido operativo del día. Acción única desde este módulo: no
     * mezcla la gestión del recorrido con el catálogo de Acopiadores.
     */
    public function abrirRecorrido(AbrirRecorridoRequest $request, RutaModel $ruta): RedirectResponse
    {
        Gate::authorize('update', $ruta);

        $datos = $request->validated();

        try {
            $recorrido = $this->abrirRecorrido->execute(
                rutaId: $ruta->id,
                acopiadorId: (int) $datos['acopiador_id'],
                fechaOperativa: isset($datos['fecha_operativa']) ? new DateTimeImmutable($datos['fecha_operativa']) : null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withErrors(['acopiador_id' => $e->getMessage()]);
        }

        return redirect()->back()
            ->with('success', "Recorrido #{$recorrido->id()} asignado correctamente.")
            ->with('zonaAbierta', $ruta->zona_id)
            ->with('rutaAbierta', $ruta->id);
    }
}
