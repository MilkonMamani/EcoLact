<?php

namespace App\Http\Controllers\Web;

use App\Application\Acopiador\AcopiadoresFiltro;
use App\Application\Acopiador\ActualizarAcopiador;
use App\Application\Acopiador\Contracts\AcopiadoresListadoQuery;
use App\Application\Acopiador\DesactivarAcopiador;
use App\Application\Acopiador\ObtenerAcopiador;
use App\Application\Acopiador\ObtenerAcopiadores;
use App\Application\Acopiador\ObtenerZonaHistorialAcopiador;
use App\Application\Acopiador\ReactivarAcopiador;
use App\Application\Acopiador\RegistrarAcopiador;
use App\Application\Acopio\ObtenerEntregasAcopiador;
use App\Application\Pago\ObtenerControlPeriodoTrabajador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Acopiador\ReactivarAcopiadorRequest;
use App\Http\Requests\Acopiador\StoreAcopiadorRequest;
use App\Http\Requests\Acopiador\UpdateAcopiadorRequest;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo Acopiador.
 *
 * Desactivar/Reactivar son operaciones explícitas y separadas de update():
 * ActualizarAcopiador ya no admite un parámetro "estado" (Etapa 07.2).
 */
final class AcopiadorController extends Controller
{
    public function __construct(
        private readonly RegistrarAcopiador $registrarAcopiador,
        private readonly ActualizarAcopiador $actualizarAcopiador,
        private readonly DesactivarAcopiador $desactivarAcopiador,
        private readonly ReactivarAcopiador $reactivarAcopiador,
        private readonly ObtenerAcopiador $obtenerAcopiador,
        private readonly ObtenerAcopiadores $obtenerAcopiadores,
        private readonly AcopiadoresListadoQuery $acopiadoresListadoQuery,
        private readonly ObtenerEntregasAcopiador $obtenerEntregasAcopiador,
        private readonly ObtenerZonaHistorialAcopiador $obtenerZonaHistorialAcopiador,
        private readonly ObtenerControlPeriodoTrabajador $obtenerControlPeriodo,
    ) {}

    /**
     * AcopiadorPolicy::viewAny solo admite ADMINISTRADOR (vía Gate::before),
     * así que este listado siempre es global, con KPIs/búsqueda/filtros.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AcopiadorModel::class);

        $filtro = AcopiadoresFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
        );

        return view('admin.acopiadores.index', ['datos' => $this->acopiadoresListadoQuery->obtener($filtro)]);
    }

    public function show(AcopiadorModel $acopiador): View
    {
        Gate::authorize('view', $acopiador);

        return view('admin.acopiadores.show', [
            'acopiador' => $this->obtenerAcopiador->execute($acopiador->id),
            'entregas' => $this->obtenerEntregasAcopiador->execute($acopiador->id),
            'zonaHistorial' => $this->obtenerZonaHistorialAcopiador->execute($acopiador->id),
            'zonas' => ZonaModel::pluck('nombre', 'id'),
            'controlPeriodo' => $this->obtenerControlPeriodo->execute($acopiador->user_id),
        ]);
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
            fechaIngreso: isset($datos['fecha_ingreso']) ? new DateTimeImmutable($datos['fecha_ingreso']) : null,
        );

        return redirect()->back()->with('success', 'Acopiador registrado correctamente.');
    }

    public function update(UpdateAcopiadorRequest $request, AcopiadorModel $acopiador): RedirectResponse
    {
        Gate::authorize('update', $acopiador);

        $datos = $request->validated();

        $this->actualizarAcopiador->execute(
            acopiadorId: $acopiador->id,
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            telefono: $datos['telefono'] ?? null,
            nuevaZonaId: isset($datos['zona_id']) ? (int) $datos['zona_id'] : null,
            motivoCambioZona: $datos['motivo_cambio_zona'] ?? null,
        );

        return redirect()->back()->with('success', 'Acopiador actualizado correctamente.');
    }

    public function desactivar(AcopiadorModel $acopiador): RedirectResponse
    {
        Gate::authorize('update', $acopiador);

        $this->desactivarAcopiador->execute($acopiador->id);

        return redirect()->back()->with('success', 'Acopiador desactivado correctamente.');
    }

    public function reactivar(ReactivarAcopiadorRequest $request, AcopiadorModel $acopiador): RedirectResponse
    {
        Gate::authorize('update', $acopiador);

        $this->reactivarAcopiador->execute($acopiador->id, (int) $request->validated('zona_id'));

        return redirect()->back()->with('success', 'Acopiador reactivado correctamente.');
    }
}
