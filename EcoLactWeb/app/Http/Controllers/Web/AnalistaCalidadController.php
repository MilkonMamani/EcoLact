<?php

namespace App\Http\Controllers\Web;

use App\Application\AnalistaCalidad\ActualizarAnalistaCalidad;
use App\Application\AnalistaCalidad\DesactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\ObtenerAnalistaCalidad;
use App\Application\AnalistaCalidad\ObtenerAnalistasCalidad;
use App\Application\AnalistaCalidad\ReactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\RegistrarAnalistaCalidad;
use App\Application\Pago\ObtenerControlPeriodoTrabajador;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalistaCalidad\StoreAnalistaCalidadRequest;
use App\Http\Requests\AnalistaCalidad\UpdateAnalistaCalidadRequest;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo AnalistaCalidad. Exclusivo de
 * ADMINISTRADOR (Gate::before + AnalistaCalidadPolicy): sin zona, el
 * analista controla calidad en cualquier zona.
 */
final class AnalistaCalidadController extends Controller
{
    public function __construct(
        private readonly RegistrarAnalistaCalidad $registrarAnalistaCalidad,
        private readonly ActualizarAnalistaCalidad $actualizarAnalistaCalidad,
        private readonly DesactivarAnalistaCalidad $desactivarAnalistaCalidad,
        private readonly ReactivarAnalistaCalidad $reactivarAnalistaCalidad,
        private readonly ObtenerAnalistaCalidad $obtenerAnalistaCalidad,
        private readonly ObtenerAnalistasCalidad $obtenerAnalistasCalidad,
        private readonly ObtenerControlPeriodoTrabajador $obtenerControlPeriodo,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', AnalistaCalidadModel::class);

        return view('admin.analistas-calidad.index', [
            'analistas' => $this->obtenerAnalistasCalidad->execute(),
        ]);
    }

    public function show(AnalistaCalidadModel $analista): View
    {
        Gate::authorize('view', $analista);

        return view('admin.analistas-calidad.show', [
            'analista' => $this->obtenerAnalistaCalidad->execute($analista->id),
            'controlPeriodo' => $this->obtenerControlPeriodo->execute($analista->user_id),
        ]);
    }

    public function store(StoreAnalistaCalidadRequest $request): RedirectResponse
    {
        Gate::authorize('create', AnalistaCalidadModel::class);

        $datos = $request->validated();

        $this->registrarAnalistaCalidad->execute(
            dni: $datos['dni'],
            nombres: $datos['nombres'],
            apellidos: $datos['apellidos'],
            telefono: $datos['telefono'] ?? null,
            username: $datos['username'],
            password: $datos['password'],
            fechaIngreso: isset($datos['fecha_ingreso']) ? new DateTimeImmutable($datos['fecha_ingreso']) : null,
        );

        return redirect()->back()->with('success', 'Analista de calidad registrado correctamente.');
    }

    public function update(UpdateAnalistaCalidadRequest $request, AnalistaCalidadModel $analista): RedirectResponse
    {
        Gate::authorize('update', $analista);

        $datos = $request->validated();

        $this->actualizarAnalistaCalidad->execute(
            analistaId: $analista->id,
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        return redirect()->back()->with('success', 'Analista de calidad actualizado correctamente.');
    }

    public function desactivar(AnalistaCalidadModel $analista): RedirectResponse
    {
        Gate::authorize('update', $analista);

        $this->desactivarAnalistaCalidad->execute($analista->id);

        return redirect()->back()->with('success', 'Analista de calidad desactivado correctamente.');
    }

    public function reactivar(AnalistaCalidadModel $analista): RedirectResponse
    {
        Gate::authorize('update', $analista);

        $this->reactivarAnalistaCalidad->execute($analista->id);

        return redirect()->back()->with('success', 'Analista de calidad reactivado correctamente.');
    }
}
