<?php

namespace App\Http\Controllers\Web;

use App\Application\Acopiador\AcopiadoresFiltro;
use App\Application\Acopiador\AcopiadorPanelData;
use App\Application\Acopiador\ActualizarAcopiador;
use App\Application\Acopiador\Contracts\AcopiadoresListadoQuery;
use App\Application\Acopiador\Contracts\AcopiadorPanelQuery;
use App\Application\Acopiador\DesactivarAcopiador;
use App\Application\Acopiador\ReactivarAcopiador;
use App\Application\Acopiador\RegistrarAcopiador;
use App\Application\Pago\ObtenerControlPeriodoTrabajador;
use App\Domain\Acopiador\Acopiador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Acopiador\ReactivarAcopiadorRequest;
use App\Http\Requests\Acopiador\StoreAcopiadorRequest;
use App\Http\Requests\Acopiador\UpdateAcopiadorRequest;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo Acopiador.
 *
 * La pantalla es de dos columnas (igual que Proveedores): listado paginado
 * a la izquierda y el panel del acopiador seleccionado a la derecha, que se
 * pinta en el servidor en la carga inicial (?acopiador=ID) y se pide por
 * fetch a panel() al elegir otra fila.
 *
 * Desactivar/Reactivar son operaciones explícitas y separadas de update():
 * ActualizarAcopiador no admite un parámetro "estado" (Etapa 07.2). El
 * switch de estado del listado llama a estas MISMAS acciones (por fetch,
 * pidiendo JSON); no existe otro camino para cambiar el estado.
 */
final class AcopiadorController extends Controller
{
    private const POR_PAGINA = 12;

    public function __construct(
        private readonly RegistrarAcopiador $registrarAcopiador,
        private readonly ActualizarAcopiador $actualizarAcopiador,
        private readonly DesactivarAcopiador $desactivarAcopiador,
        private readonly ReactivarAcopiador $reactivarAcopiador,
        private readonly AcopiadoresListadoQuery $acopiadoresListadoQuery,
        private readonly AcopiadorPanelQuery $acopiadorPanelQuery,
        private readonly ObtenerControlPeriodoTrabajador $obtenerControlPeriodo,
    ) {}

    /**
     * AcopiadorPolicy::viewAny solo admite ADMINISTRADOR (vía Gate::before).
     * Sin ?acopiador= se selecciona el primero de la página.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AcopiadorModel::class);

        $filtro = AcopiadoresFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
        );

        $datos = $this->acopiadoresListadoQuery->paginar($filtro, self::POR_PAGINA);
        $seleccionadoId = $request->filled('acopiador')
            ? $request->integer('acopiador')
            : ($datos->filas->first()['id'] ?? null);

        return view('admin.acopiadores.index', [
            'datos' => $datos,
            ...$this->datosPanel($seleccionadoId, $request),
        ]);
    }

    /** Fragmento HTML del panel derecho (lo pide admin-fichas.js al elegir una fila). */
    public function panel(Request $request, AcopiadorModel $acopiador): View
    {
        Gate::authorize('view', $acopiador);

        return view('admin.acopiadores._panel', [
            ...$this->datosPanel($acopiador->id, $request),
            'zonasActivas' => $this->zonasActivas(),
        ]);
    }

    /** Ya no hay página de detalle aparte: el acopiador se ve en el panel del listado. */
    public function show(AcopiadorModel $acopiador): RedirectResponse
    {
        Gate::authorize('view', $acopiador);

        return redirect()->route('admin.acopiadores.index', ['acopiador' => $acopiador->id]);
    }

    public function store(StoreAcopiadorRequest $request): RedirectResponse
    {
        Gate::authorize('create', AcopiadorModel::class);

        $datos = $request->validated();

        $this->registrarAcopiador->execute(
            dni: $datos['dni'],
            nombres: $datos['nombres'],
            apellidos: $datos['apellidos'],
            telefono: $datos['telefono'] ?? null,
            zonaId: (int) $datos['zona_id'],
            username: $datos['username'],
            password: $datos['password'],
        );

        return redirect()->back()->with('success', 'Acopiador registrado correctamente.');
    }

    public function update(UpdateAcopiadorRequest $request, AcopiadorModel $acopiador): RedirectResponse
    {
        Gate::authorize('update', $acopiador);

        $datos = $request->validated();

        try {
            $this->actualizarAcopiador->execute(
                acopiadorId: $acopiador->id,
                nombres: $datos['nombres'] ?? null,
                apellidos: $datos['apellidos'] ?? null,
                telefono: $datos['telefono'] ?? null,
                nuevaZonaId: isset($datos['zona_id']) ? (int) $datos['zona_id'] : null,
                motivoCambioZona: $datos['motivo_cambio_zona'] ?? null,
            );
        } catch (DomainException $error) {
            return redirect()->back()->with('error', $error->getMessage());
        }

        return redirect()->back()->with('success', 'Acopiador actualizado correctamente.');
    }

    public function desactivar(Request $request, AcopiadorModel $acopiador): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $acopiador);

        return $this->responderCambioDeEstado(
            $request,
            fn (): Acopiador => $this->desactivarAcopiador->execute($acopiador->id),
            'Acopiador desactivado correctamente.',
        );
    }

    /** Única vía de reactivación (el modal del switch y cualquier otro formulario llegan aquí). */
    public function reactivar(ReactivarAcopiadorRequest $request, AcopiadorModel $acopiador): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $acopiador);

        return $this->responderCambioDeEstado(
            $request,
            fn (): Acopiador => $this->reactivarAcopiador->execute($acopiador->id, (int) $request->validated('zona_id')),
            'Acopiador reactivado correctamente.',
        );
    }

    /**
     * Ejecuta el Use Case y responde en JSON (switch del listado) o con
     * redirección (formularios clásicos). Una regla de negocio incumplida
     * (p. ej. ya estaba activo) es un 422 con su mensaje, nunca un 500.
     *
     * @param  callable(): Acopiador  $cambio
     */
    private function responderCambioDeEstado(Request $request, callable $cambio, string $mensaje): JsonResponse|RedirectResponse
    {
        try {
            $acopiador = $cambio();
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
            'acopiador' => [
                'id' => $acopiador->id(),
                'estado' => $acopiador->estado()->value,
                'zonaId' => $acopiador->zonaId(),
                'zonaNombre' => ZonaModel::whereKey($acopiador->zonaId())->value('nombre'),
            ],
        ]);
    }

    /** @return array{panel: ?AcopiadorPanelData, controlPeriodo: mixed} */
    private function datosPanel(?int $acopiadorId, Request $request): array
    {
        $panel = $acopiadorId !== null ? $this->acopiadorPanelQuery->obtener($acopiadorId) : null;

        return [
            'panel' => $panel,
            'controlPeriodo' => $panel !== null
                ? $this->obtenerControlPeriodo->execute($panel->userId, $request->string('mes')->toString() ?: null)
                : null,
        ];
    }

    /** @return list<array{id: int, nombre: string}> */
    private function zonasActivas(): array
    {
        return ZonaModel::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (ZonaModel $zona): array => ['id' => (int) $zona->id, 'nombre' => $zona->nombre])
            ->all();
    }
}
