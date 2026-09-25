<?php

namespace App\Http\Controllers\Web;

use App\Application\Pago\ObtenerControlPeriodoTrabajador;
use App\Application\PersonalVentas\ActualizarPersonalVentas;
use App\Application\PersonalVentas\DesactivarPersonalVentas;
use App\Application\PersonalVentas\ObtenerListaPersonalVentas;
use App\Application\PersonalVentas\ObtenerPersonalVentas;
use App\Application\PersonalVentas\ReactivarPersonalVentas;
use App\Application\PersonalVentas\RegistrarPersonalVentas;
use App\Http\Controllers\Controller;
use App\Http\Requests\PersonalVentas\StorePersonalVentasRequest;
use App\Http\Requests\PersonalVentas\UpdatePersonalVentasRequest;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Puente HTTP -> Application para el módulo PersonalVentas. Exclusivo de
 * ADMINISTRADOR (Gate::before + PersonalVentasPolicy): sin zona.
 */
final class PersonalVentasController extends Controller
{
    public function __construct(
        private readonly RegistrarPersonalVentas $registrarPersonalVentas,
        private readonly ActualizarPersonalVentas $actualizarPersonalVentas,
        private readonly DesactivarPersonalVentas $desactivarPersonalVentas,
        private readonly ReactivarPersonalVentas $reactivarPersonalVentas,
        private readonly ObtenerPersonalVentas $obtenerPersonalVentas,
        private readonly ObtenerListaPersonalVentas $obtenerListaPersonalVentas,
        private readonly ObtenerControlPeriodoTrabajador $obtenerControlPeriodo,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', PersonalVentasModel::class);

        return view('admin.personal-ventas.index', [
            'personal' => $this->obtenerListaPersonalVentas->execute(),
        ]);
    }

    public function show(PersonalVentasModel $personalVenta, ?Request $request = null): View
    {
        Gate::authorize('view', $personalVenta);

        return view('admin.personal-ventas.show', [
            'personal' => $this->obtenerPersonalVentas->execute($personalVenta->id),
            'controlPeriodo' => $this->obtenerControlPeriodo->execute($personalVenta->user_id, $request?->string('mes')->toString() ?: null),
        ]);
    }

    public function store(StorePersonalVentasRequest $request): RedirectResponse
    {
        Gate::authorize('create', PersonalVentasModel::class);

        $datos = $request->validated();

        $this->registrarPersonalVentas->execute(
            dni: $datos['dni'],
            nombres: $datos['nombres'],
            apellidos: $datos['apellidos'],
            telefono: $datos['telefono'] ?? null,
            username: $datos['username'],
            password: $datos['password'],
            fechaIngreso: isset($datos['fecha_ingreso']) ? new DateTimeImmutable($datos['fecha_ingreso']) : null,
        );

        return redirect()->back()->with('success', 'Personal de ventas registrado correctamente.');
    }

    public function update(UpdatePersonalVentasRequest $request, PersonalVentasModel $personalVenta): RedirectResponse
    {
        Gate::authorize('update', $personalVenta);

        $datos = $request->validated();

        $this->actualizarPersonalVentas->execute(
            personalId: $personalVenta->id,
            nombres: $datos['nombres'] ?? null,
            apellidos: $datos['apellidos'] ?? null,
            telefono: $datos['telefono'] ?? null,
        );

        return redirect()->back()->with('success', 'Personal de ventas actualizado correctamente.');
    }

    public function desactivar(PersonalVentasModel $personalVenta): RedirectResponse
    {
        Gate::authorize('update', $personalVenta);

        $this->desactivarPersonalVentas->execute($personalVenta->id);

        return redirect()->back()->with('success', 'Personal de ventas desactivado correctamente.');
    }

    public function reactivar(PersonalVentasModel $personalVenta): RedirectResponse
    {
        Gate::authorize('update', $personalVenta);

        $this->reactivarPersonalVentas->execute($personalVenta->id);

        return redirect()->back()->with('success', 'Personal de ventas reactivado correctamente.');
    }
}
