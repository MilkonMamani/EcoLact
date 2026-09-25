<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\CorregirJornada;
use App\Application\Pago\ObtenerJornadasDelPeriodo;
use App\Application\Pago\RegistrarJornadaTrabajador;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Trabajador\TrabajadorRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pago\CorregirJornadaRequest;
use App\Http\Requests\Pago\RegistrarJornadaTrabajadorRequest;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Models\User;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Registro y corrección de jornadas (PRESENTE/FALTA) del personal: una sola
 * ruta reutilizada por las páginas de perfil y por el panel de "Pagar a
 * trabajadores". Las rutas siguen identificando al trabajador por `User`
 * (no se tocan rutas/vistas); el controller resuelve internamente su
 * `trabajador_id` a partir del `user_id`. Autorización vía la ability "pay"
 * de ObligacionPagoPolicy, igual que el resto del módulo de pagos: exclusivo
 * de ADMINISTRADOR (Gate::before).
 */
final class JornadaPersonalController extends Controller
{
    public function __construct(
        private readonly RegistrarJornadaTrabajador $registrarJornada,
        private readonly ObtenerJornadasDelPeriodo $obtenerJornadas,
        private readonly CorregirJornada $corregirJornada,
        private readonly TrabajadorRepository $trabajadores,
        private readonly JornadaPersonalRepository $jornadasRepositorio,
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
        $identidad = $this->trabajadores->buscarPorUserId($trabajador->id);

        if ($identidad === null) {
            return back()->withErrors(['jornada' => 'Este usuario no tiene una identidad laboral registrada.']);
        }

        try {
            $this->registrarJornada->execute(
                trabajadorId: $identidad->id(),
                fecha: new DateTimeImmutable($datos['fecha']),
                observacion: $datos['observacion'] ?? null,
                registradoPorUserId: $request->user()->id,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['jornada' => $exception->getMessage()]);
        }

        return back()->with('success', 'Día trabajado registrado correctamente.');
    }

    /** Corrección administrativa FALTA<->PRESENTE de una jornada ya existente, con motivo obligatorio (auditado). */
    public function corregir(CorregirJornadaRequest $request, User $trabajador, string $fecha): RedirectResponse
    {
        Gate::authorize('pay', ObligacionPagoModel::class);

        $datos = $request->validated();
        $identidad = $this->trabajadores->buscarPorUserId($trabajador->id);

        if ($identidad === null) {
            return back()->withErrors(['jornada' => 'Este usuario no tiene una identidad laboral registrada.']);
        }

        $jornada = $this->jornadasRepositorio->buscarEnFecha($identidad->id(), new DateTimeImmutable($fecha));

        if ($jornada === null || $jornada->id() === null) {
            return back()->withErrors(['jornada' => 'No existe una jornada registrada en esa fecha para corregir.']);
        }

        try {
            $this->corregirJornada->execute(
                jornadaId: $jornada->id(),
                nuevoEstado: EstadoJornada::from($datos['estado']),
                motivo: $datos['motivo'],
                adminUserId: $request->user()->id,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['jornada' => $exception->getMessage()]);
        }

        return back()->with('success', 'Jornada corregida correctamente.');
    }
}
