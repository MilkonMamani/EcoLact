<?php

namespace App\Http\Controllers\Web;

use App\Application\EncargadoPlanta\ActualizarEncargadoPlanta;
use App\Application\EncargadoPlanta\DesactivarEncargadoPlanta;
use App\Application\EncargadoPlanta\ObtenerEncargadoPlanta;
use App\Application\EncargadoPlanta\ObtenerEncargadosPlanta;
use App\Application\EncargadoPlanta\ReactivarEncargadoPlanta;
use App\Application\EncargadoPlanta\RegistrarEncargadoPlanta;
use App\Application\Pago\ObtenerControlPeriodoTrabajador;
use App\Http\Controllers\Controller;
use App\Http\Requests\EncargadoPlanta\StoreEncargadoPlantaRequest;
use App\Http\Requests\EncargadoPlanta\UpdateEncargadoPlantaRequest;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo EncargadoPlanta. Exclusivo de
 * ADMINISTRADOR (Gate::before + EncargadoPlantaPolicy): sin zona, porque el
 * encargado de planta opera sobre toda la planta.
 */
final class EncargadoPlantaController extends Controller
{
    public function __construct(
        private readonly RegistrarEncargadoPlanta $registrarEncargadoPlanta,
        private readonly ActualizarEncargadoPlanta $actualizarEncargadoPlanta,
        private readonly DesactivarEncargadoPlanta $desactivarEncargadoPlanta,
        private readonly ReactivarEncargadoPlanta $reactivarEncargadoPlanta,
        private readonly ObtenerEncargadoPlanta $obtenerEncargadoPlanta,
        private readonly ObtenerEncargadosPlanta $obtenerEncargadosPlanta,
        private readonly ObtenerControlPeriodoTrabajador $obtenerControlPeriodo,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', EncargadoPlantaModel::class);

        return view('admin.encargados-planta.index', [
            'encargados' => $this->obtenerEncargadosPlanta->execute(),
        ]);
    }

    public function show(EncargadoPlantaModel $encargado): View
    {
        Gate::authorize('view', $encargado);

        return view('admin.encargados-planta.show', [
            'encargado' => $this->obtenerEncargadoPlanta->execute($encargado->id),
            'controlPeriodo' => $this->obtenerControlPeriodo->execute($encargado->user_id),
        ]);
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
            fechaIngreso: isset($datos['fecha_ingreso']) ? new DateTimeImmutable($datos['fecha_ingreso']) : null,
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

    public function desactivar(EncargadoPlantaModel $encargado): RedirectResponse
    {
        Gate::authorize('update', $encargado);

        $this->desactivarEncargadoPlanta->execute($encargado->id);

        return redirect()->back()->with('success', 'Encargado de planta desactivado correctamente.');
    }

    public function reactivar(EncargadoPlantaModel $encargado): RedirectResponse
    {
        Gate::authorize('update', $encargado);

        $this->reactivarEncargadoPlanta->execute($encargado->id);

        return redirect()->back()->with('success', 'Encargado de planta reactivado correctamente.');
    }
}
