<?php

namespace App\Http\Controllers\Web;

use App\Application\Ruta\GuardarAsignacionProveedor;
use App\Application\Shared\CampoActualizable;
use App\Application\Zona\ActualizarZona;
use App\Application\Zona\Contracts\ZonaPanelQuery;
use App\Application\Zona\Contracts\ZonaProveedoresQuery;
use App\Application\Zona\EliminarZona;
use App\Application\Zona\GuardarDelimitacionZona;
use App\Application\Zona\MoverProveedorDeZona;
use App\Application\Zona\ProveedorAsignadoFila;
use App\Application\Zona\RegistrarZona;
use App\Application\Zona\UbicacionFueraDeZona;
use App\Application\Zona\ZonaMapService;
use App\Application\Zona\ZonaPanelData;
use App\Application\Zona\ZonasFiltro;
use App\Application\Zona\ZonasMapaData;
use App\Domain\Ruta\RutaRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Zona\GuardarAsignacionProveedorRequest;
use App\Http\Requests\Zona\MoverProveedorDeZonaRequest;
use App\Http\Requests\Zona\StoreZonaRequest;
use App\Http\Requests\Zona\UpdateZonaRequest;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para Zonas. La pantalla es de dos columnas: a la
 * izquierda el buscador de proveedores y el mapa general (el centro del módulo:
 * se elige una zona tocando su polígono, etiqueta o leyenda) y a la derecha el
 * panel de la zona seleccionada, pintado en el servidor en la carga inicial
 * (?zona=ID) y pedido por fetch a panel() al elegir otra.
 *
 * El menú del panel activa/desactiva con update() (ActualizarZona conserva su
 * regla: no se desactiva una zona con acopiadores o proveedores activos).
 */
final class ZonaController extends Controller
{
    /**
     * Clave del panel especial de proveedores activos que todavía no tienen
     * zona ni ruta (se abre desde el aviso "Proveedores sin zona").
     */
    private const SIN_ASIGNAR = 'sin-asignar';

    public function __construct(
        private readonly RegistrarZona $registrarZona,
        private readonly ActualizarZona $actualizarZona,
        private readonly EliminarZona $eliminarZona,
        private readonly ZonaPanelQuery $zonaPanelQuery,
        private readonly ZonaProveedoresQuery $zonaProveedoresQuery,
        private readonly RutaRepository $rutas,
        private readonly GuardarAsignacionProveedor $guardarAsignacionProveedor,
        private readonly GuardarDelimitacionZona $guardarDelimitacionZona,
        private readonly ZonaMapService $zonaMapService,
        private readonly MoverProveedorDeZona $moverProveedorDeZona,
    ) {}

    /**
     * Sin ?zona= se selecciona la primera zona (por nombre); ?zona=sin-asignar abre el panel de
     * proveedores sin zona. ?proveedor=ID lo lee admin-zonas.js para volver a enfocar ese pin.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ZonaModel::class);

        $mapaZonas = $this->zonaMapService->obtener(ZonasFiltro::resolver(busqueda: null, estado: null));

        $solicitada = $request->string('zona')->toString();
        $seleccion = match (true) {
            $solicitada === self::SIN_ASIGNAR => self::SIN_ASIGNAR,
            $solicitada !== '' && ZonaModel::whereKey((int) $solicitada)->exists() => (int) $solicitada,
            default => $mapaZonas->zonas[0]['id'] ?? null,
        };

        return view('admin.zonas.index', [
            'mapaZonas' => $mapaZonas,
            'sinAsignar' => $this->zonaProveedoresQuery->contarSinAsignar(),
            'catalogo' => $this->catalogoParaModal($mapaZonas),
            ...$this->datosPanel($seleccion),
        ]);
    }

    /** Ya no hay página de detalle aparte: la zona se ve en el panel del listado. */
    public function show(ZonaModel $zona): RedirectResponse
    {
        Gate::authorize('view', $zona);

        return redirect()->route('admin.zonas.index', ['zona' => $zona->id]);
    }

    /** Fragmento HTML del panel derecho (lo pide admin-fichas.js al elegir una fila). */
    public function panel(ZonaModel $zona): View
    {
        Gate::authorize('view', $zona);

        return view('admin.zonas._panel', $this->datosPanel($zona->id));
    }

    /** Fragmento HTML del panel de proveedores activos sin zona ni ruta. */
    public function panelSinAsignar(): View
    {
        Gate::authorize('viewAny', ZonaModel::class);

        return view('admin.zonas._panel', $this->datosPanel(self::SIN_ASIGNAR));
    }

    /**
     * Búsqueda de proveedores por nombre o DNI para abrir el modal de
     * asignación. Devuelve, por proveedor, los datos con los que el modal
     * se precarga (zona/ruta/ubicación actuales, si las tiene).
     */
    public function buscarProveedores(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ZonaModel::class);

        $filas = $this->zonaProveedoresQuery->buscar($request->string('q')->toString());

        return response()->json(array_map(fn ($fila): array => $fila->paraModal(), $filas));
    }

    /** Una zona nueva nace siempre ACTIVA: el formulario ya no pregunta el estado. */
    public function store(StoreZonaRequest $request): RedirectResponse
    {
        Gate::authorize('create', ZonaModel::class);

        $datos = $request->validated();

        $zona = $this->registrarZona->execute(
            nombre: $datos['nombre'],
            descripcion: $datos['descripcion'] ?? null,
            estado: 'ACTIVO',
        );

        return redirect()->route('admin.zonas.index', ['zona' => $zona->id()])
            ->with('success', 'Zona registrada correctamente.');
    }

    /**
     * descripcion es nullable en el esquema: "no enviada" (conservar) y
     * "enviada como null" (limpiar) son casos distintos que ActualizarZona
     * distingue vía CampoActualizable (ver Etapa 07.2). $request->has()
     * detecta si la clave llegó en la petición, aunque su valor sea null.
     *
     * Responde JSON al switch del listado (admin-estado-switch.js) y
     * redirección a los formularios. Una regla de negocio incumplida (p. ej.
     * desactivar con proveedores o acopiadores activos) es un 422 con su
     * mensaje, o un flash de error; nunca un 500.
     */
    public function update(UpdateZonaRequest $request, ZonaModel $zona): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $zona);

        $datos = $request->validated();

        try {
            $actualizada = $this->actualizarZona->execute(
                zonaId: $zona->id,
                nombre: $datos['nombre'] ?? null,
                descripcion: $request->has('descripcion') ? CampoActualizable::conValor($datos['descripcion'] ?? null) : null,
                estado: $datos['estado'] ?? null,
            );

            // "Editar delimitación" envía el color y el polígono junto con el formulario de la zona.
            if ($request->has('color_mapa')) {
                $this->guardarDelimitacionZona->execute(
                    zonaId: $zona->id,
                    colorMapa: $datos['color_mapa'],
                    poligonoGeoJson: $datos['poligono_geojson'] ?? null,
                );
            }
        } catch (DomainException $error) {
            return $request->expectsJson()
                ? response()->json(['message' => $error->getMessage()], 422)
                : redirect()->route('admin.zonas.index', ['zona' => $zona->id])->with('error', $error->getMessage());
        }

        $mensaje = match (true) {
            ($datos['estado'] ?? $zona->estado) === $zona->estado => 'Zona actualizada correctamente.',
            $datos['estado'] === 'ACTIVO' => 'Zona activada correctamente.',
            default => 'Zona desactivada correctamente.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensaje,
                'zona' => ['id' => $actualizada->id(), 'estado' => $actualizada->estado()],
            ]);
        }

        return redirect()->route('admin.zonas.index', ['zona' => $zona->id])->with('success', $mensaje);
    }

    public function destroy(ZonaModel $zona): RedirectResponse
    {
        Gate::authorize('delete', $zona);

        try {
            $this->eliminarZona->execute($zona->id);
        } catch (DomainException $e) {
            return redirect()->route('admin.zonas.index', ['zona' => $zona->id])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('admin.zonas.index')
            ->with('success', 'Zona eliminada correctamente.');
    }

    /**
     * Guarda el modal de asignación: zona + ruta + punto de recolección +
     * referencia. Responde JSON: el modal muestra los errores bajo cada
     * campo (una ubicación fuera de la zona, junto al mapa) y, al guardar,
     * la pantalla se recarga sobre la zona destino con el pin del proveedor
     * enfocado (?proveedor=) y el mensaje de éxito (flash de sesión).
     */
    public function guardarAsignacion(GuardarAsignacionProveedorRequest $request, ProveedorModel $proveedor): JsonResponse
    {
        Gate::authorize('update', RutaModel::findOrFail($request->integer('ruta_id')));

        $datos = $request->validated();

        try {
            $this->guardarAsignacionProveedor->execute(
                proveedorId: $proveedor->id,
                rutaId: (int) $datos['ruta_id'],
                latitud: isset($datos['latitud']) ? (float) $datos['latitud'] : null,
                longitud: isset($datos['longitud']) ? (float) $datos['longitud'] : null,
                referenciaUbicacion: $datos['referencia_ubicacion'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [($e instanceof UbicacionFueraDeZona ? 'latitud' : 'ruta_id') => [$e->getMessage()]],
            ], 422);
        }

        session()->flash('success', 'Asignación guardada correctamente.');

        return response()->json([
            'ok' => true,
            'redirect' => route('admin.zonas.index', ['zona' => (int) $datos['zona_id'], 'proveedor' => $proveedor->id]),
        ]);
    }

    /**
     * "Mover ubicación" de un pin del mapa general. MoverProveedorDeZona vuelve a calcular la
     * zona con las delimitaciones guardadas (misma zona: solo coordenadas; otra zona: cambio de
     * ruta y zona, auditado y en una transacción). La respuesta trae el mapa recalculado para
     * que la pantalla actualice pines, colores y contadores sin recargar.
     */
    public function moverProveedor(MoverProveedorDeZonaRequest $request, ProveedorModel $proveedor): JsonResponse
    {
        Gate::authorize('update', $proveedor);

        $datos = $request->validated();

        try {
            $movimiento = $this->moverProveedorDeZona->execute(
                proveedorId: $proveedor->id,
                latitud: (float) $datos['latitud'],
                longitud: (float) $datos['longitud'],
                rutaDestinoId: isset($datos['ruta_id']) ? (int) $datos['ruta_id'] : null,
                zonaEsperadaId: isset($datos['zona_id']) ? (int) $datos['zona_id'] : null,
                userId: $request->user()->id,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (DomainException $error) {
            return response()->json(['message' => $error->getMessage()], 422);
        }

        return response()->json([
            'message' => $movimiento->cambioZona()
                ? "{$movimiento->proveedorNombre} pasó de {$movimiento->zonaAnteriorNombre} a {$movimiento->zonaNuevaNombre}."
                : "Ubicación de {$movimiento->proveedorNombre} actualizada.",
            'movimiento' => $movimiento->toArray(),
            'mapa' => $this->zonaMapService->obtener(ZonasFiltro::resolver(busqueda: null, estado: null))->toArray(),
        ]);
    }

    /**
     * @return array{panel: ?ZonaPanelData, proveedoresSinZona: ?list<ProveedorAsignadoFila>}
     */
    private function datosPanel(int|string|null $seleccion): array
    {
        return [
            'panel' => is_int($seleccion) ? $this->zonaPanelQuery->obtener($seleccion) : null,
            'proveedoresSinZona' => $seleccion === self::SIN_ASIGNAR ? $this->zonaProveedoresQuery->sinAsignar() : null,
        ];
    }

    /**
     * Zonas activas con sus rutas activas para el modal de asignación. Sale de
     * los MISMOS datos del mapa general (ZonaMapService): ids, nombres, colores
     * y delimitaciones son idénticos en los dos mapas, y con esa geometría el
     * modal pinta las zonas y detecta en cuál cae el click. Una sola consulta
     * de rutas para todas las zonas (sin N+1).
     *
     * @return list<array{id: int, nombre: string, color: string, geometry: ?array<string, mixed>, rutas: list<array{id: int, nombre: string}>}>
     */
    private function catalogoParaModal(ZonasMapaData $mapaZonas): array
    {
        $rutasPorZona = collect($this->rutas->listarActivas())->groupBy(fn ($ruta) => $ruta->zonaId());

        return collect($mapaZonas->zonas)
            ->filter(fn (array $zona): bool => $zona['estado'] === 'ACTIVO')
            ->map(fn (array $zona): array => [
                'id' => $zona['id'],
                'nombre' => $zona['nombre'],
                'color' => $zona['color'],
                'geometry' => $zona['geometry'],
                'rutas' => ($rutasPorZona->get($zona['id']) ?? collect())
                    ->map(fn ($ruta): array => ['id' => $ruta->id(), 'nombre' => $ruta->nombre()])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
