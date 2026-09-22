<?php

namespace App\Http\Controllers\Web;

use App\Application\Acopiador\ObtenerAcopiadores;
use App\Application\Proveedor\ObtenerProveedores;
use App\Application\Ruta\GuardarAsignacionProveedor;
use App\Application\Ruta\ObtenerRutasPorZona;
use App\Application\Shared\CampoActualizable;
use App\Application\Zona\ActualizarZona;
use App\Application\Zona\Contracts\ZonaProveedoresQuery;
use App\Application\Zona\Contracts\ZonasListadoQuery;
use App\Application\Zona\EliminarZona;
use App\Application\Zona\ObtenerZona;
use App\Application\Zona\ObtenerZonas;
use App\Application\Zona\RegistrarZona;
use App\Application\Zona\ZonasFiltro;
use App\Domain\Ruta\RutaRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Zona\GuardarAsignacionProveedorRequest;
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

final class ZonaController extends Controller
{
    /**
     * Clave de la entrada especial "Sin asignar" del panel de zonas: agrupa
     * a los proveedores activos que todavía no tienen zona ni ruta.
     */
    private const SIN_ASIGNAR = 'sin-asignar';

    public function __construct(
        private readonly RegistrarZona $registrarZona,
        private readonly ActualizarZona $actualizarZona,
        private readonly EliminarZona $eliminarZona,
        private readonly ObtenerZona $obtenerZona,
        private readonly ObtenerZonas $obtenerZonas,
        private readonly ZonasListadoQuery $zonasListadoQuery,
        private readonly ZonaProveedoresQuery $zonaProveedoresQuery,
        private readonly ObtenerProveedores $obtenerProveedores,
        private readonly ObtenerAcopiadores $obtenerAcopiadores,
        private readonly ObtenerRutasPorZona $obtenerRutasPorZona,
        private readonly RutaRepository $rutas,
        private readonly GuardarAsignacionProveedor $guardarAsignacionProveedor,
    ) {}

    /**
     * Pantalla maestro-detalle: panel de zonas a la izquierda y, a la
     * derecha, la tabla de proveedores de la zona seleccionada (parámetro
     * ?zona=<id>|sin-asignar; por defecto la primera zona).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ZonaModel::class);

        $datos = $this->zonasListadoQuery->obtener(ZonasFiltro::resolver(busqueda: null, estado: null));

        $seleccion = $this->resolverSeleccion($request->string('zona')->toString(), $datos->filas);

        return view('admin.zonas.index', [
            'datos' => $datos,
            'sinAsignar' => $this->zonaProveedoresQuery->contarSinAsignar(),
            'catalogo' => $this->catalogoParaModal($datos->filas),
            ...$this->datosPanel($seleccion, $datos->filas),
        ]);
    }

    public function show(ZonaModel $zona): View
    {
        Gate::authorize('view', $zona);

        return view('admin.zonas.show', [
            'zona' => $this->obtenerZona->execute($zona->id),
            'proveedores' => $this->obtenerProveedores->execute($zona->id),
            'acopiadores' => $this->obtenerAcopiadores->execute($zona->id),
            'rutas' => $this->obtenerRutasPorZona->execute($zona->id),
        ]);
    }

    /**
     * Fragmento HTML (sin layout) del panel derecho de una zona: cabecera,
     * rutas y tabla de proveedores. Es el mismo parcial que renderiza el
     * index, así la selección de zona y el refresco tras reordenar no
     * recargan la página completa.
     */
    public function panel(ZonaModel $zona): View
    {
        Gate::authorize('view', $zona);

        $datos = $this->zonasListadoQuery->obtener(ZonasFiltro::resolver(busqueda: null, estado: null));

        return view('admin.zonas._panel', $this->datosPanel($zona->id, $datos->filas));
    }

    /**
     * Fragmento HTML del panel de proveedores activos sin zona ni ruta.
     */
    public function panelSinAsignar(): View
    {
        Gate::authorize('viewAny', ZonaModel::class);

        $datos = $this->zonasListadoQuery->obtener(ZonasFiltro::resolver(busqueda: null, estado: null));

        return view('admin.zonas._panel', $this->datosPanel(self::SIN_ASIGNAR, $datos->filas));
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

    public function store(StoreZonaRequest $request): RedirectResponse
    {
        Gate::authorize('create', ZonaModel::class);

        $datos = $request->validated();

        $zona = $this->registrarZona->execute(
            nombre: $datos['nombre'],
            descripcion: $datos['descripcion'] ?? null,
            estado: $datos['estado'],
        );

        return redirect()->route('admin.zonas.index', ['zona' => $zona->id()])
            ->with('success', 'Zona registrada correctamente.');
    }

    /**
     * descripcion es nullable en el esquema: "no enviada" (conservar) y
     * "enviada como null" (limpiar) son casos distintos que ActualizarZona
     * distingue vía CampoActualizable (ver Etapa 07.2). $request->has()
     * detecta si la clave llegó en la petición, aunque su valor sea null;
     * $request->input()/validated() por sí solos no permiten esa distinción.
     */
    public function update(UpdateZonaRequest $request, ZonaModel $zona): RedirectResponse
    {
        Gate::authorize('update', $zona);

        $datos = $request->validated();

        $this->actualizarZona->execute(
            zonaId: $zona->id,
            nombre: $datos['nombre'] ?? null,
            descripcion: $request->has('descripcion') ? CampoActualizable::conValor($datos['descripcion'] ?? null) : null,
            estado: $datos['estado'] ?? null,
        );

        return redirect()->route('admin.zonas.index', ['zona' => $zona->id])
            ->with('success', 'Zona actualizada correctamente.');
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
     * campo y, al guardar, la pantalla se recarga sobre la zona destino
     * con el mensaje de éxito (flash de sesión).
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
                'errors' => ['ruta_id' => [$e->getMessage()]],
            ], 422);
        }

        session()->flash('success', 'Asignación guardada correctamente.');

        return response()->json([
            'ok' => true,
            'redirect' => route('admin.zonas.index', ['zona' => (int) $datos['zona_id']]),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{seleccion: int|string, zonaSeleccionada: ?array<string, mixed>, proveedores: array}
     */
    private function datosPanel(int|string $seleccion, array $filas): array
    {
        $sinAsignar = $seleccion === self::SIN_ASIGNAR;

        return [
            'seleccion' => $seleccion,
            'zonaSeleccionada' => $sinAsignar ? null : collect($filas)->firstWhere('id', $seleccion),
            'proveedores' => $sinAsignar
                ? $this->zonaProveedoresQuery->sinAsignar()
                : $this->zonaProveedoresQuery->deZona($seleccion),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return int|string zona id, o SIN_ASIGNAR
     */
    private function resolverSeleccion(string $solicitada, array $filas): int|string
    {
        if ($solicitada === self::SIN_ASIGNAR) {
            return self::SIN_ASIGNAR;
        }

        $ids = array_column($filas, 'id');

        if ($solicitada !== '' && in_array((int) $solicitada, $ids, true)) {
            return (int) $solicitada;
        }

        return $ids[0] ?? self::SIN_ASIGNAR;
    }

    /**
     * Zonas activas con sus rutas activas, listas para los selectores
     * dependientes del modal. Una sola consulta de rutas para todas las
     * zonas (sin N+1).
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{id: int, nombre: string, rutas: list<array{id: int, nombre: string}>}>
     */
    private function catalogoParaModal(array $filas): array
    {
        $rutasPorZona = collect($this->rutas->listarActivas())->groupBy(fn ($ruta) => $ruta->zonaId());

        return collect($filas)
            ->where('estado', 'ACTIVO')
            ->map(fn (array $zona): array => [
                'id' => $zona['id'],
                'nombre' => $zona['nombre'],
                'rutas' => ($rutasPorZona->get($zona['id']) ?? collect())
                    ->map(fn ($ruta): array => ['id' => $ruta->id(), 'nombre' => $ruta->nombre()])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
