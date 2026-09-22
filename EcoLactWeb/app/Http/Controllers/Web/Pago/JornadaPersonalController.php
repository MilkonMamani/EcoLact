<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\ObtenerJornadasDelPeriodo;
use App\Application\Pago\RegistrarJornadaTrabajador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pago\RegistrarJornadaTrabajadorRequest;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Models\User;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Registro de jornadas (días trabajados) del personal: una sola ruta,
 * reutilizada por las páginas de perfil (acopiador, calidad, encargado de
 * planta, ventas, administrador) y por el panel de "Pagar a trabajadores".
 * Autorización vía la ability "pay" de ObligacionPagoPolicy, igual que el
 * resto del módulo de pagos: exclusivo de ADMINISTRADOR (Gate::before).
 */
final class JornadaPersonalController extends Controller
{
    public function __construct(
        private readonly RegistrarJornadaTrabajador $registrarJornada,
        private readonly ObtenerJornadasDelPeriodo $obtenerJornadas,
    ) {}

    /** Fragmento HTML (sin layout) del modal "Ver jornadas". */
    public function index(User $trabajador): View
    {
        Gate::authorize('pay', ObligacionPagoModel::class);

        return view('admin.pagos._modal-jornadas', [
            'trabajador' => $trabajador,
            'jornadas' => $this->obtenerJornadas->execute($trabajador->id),
        ]);
    }

    public function store(RegistrarJornadaTrabajadorRequest $request, User $trabajador): RedirectResponse
    {
        Gate::authorize('pay', ObligacionPagoModel::class);

        $datos = $request->validated();

        try {
            $this->registrarJornada->execute(
                userId: $trabajador->id,
                rolId: $trabajador->role_id,
                fecha: new DateTimeImmutable($datos['fecha']),
                observacion: $datos['observacion'] ?? null,
                registradoPorUserId: $request->user()->id,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['jornada' => $exception->getMessage()]);
        }

        return back()->with('success', 'Día trabajado registrado correctamente.');
    }
}
