<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\ObtenerAsistenciaDelDia;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Control de asistencia: quién marcó (o no) en un día dado, para todo el
 * personal remunerable. Autorización vía la misma ability "pay" de
 * ObligacionPagoPolicy que ya protege el resto del módulo de pagos/jornadas
 * (exclusivo de ADMINISTRADOR, Gate::before).
 */
final class ControlAsistenciaController extends Controller
{
    public function __construct(
        private readonly ObtenerAsistenciaDelDia $obtenerAsistencia,
        private readonly RoleRepository $roles,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('pay', ObligacionPagoModel::class);

        try {
            $fecha = $request->filled('fecha') ? new DateTimeImmutable((string) $request->string('fecha')) : DateTimeImmutable::createFromInterface(today());
        } catch (Throwable) {
            $fecha = DateTimeImmutable::createFromInterface(today());
        }

        $hoy = DateTimeImmutable::createFromInterface(today());

        if ($fecha > $hoy) {
            $fecha = $hoy;
        }

        return view('admin.asistencia.index', [
            'fecha' => $fecha,
            'esHoy' => $fecha->format('Y-m-d') === $hoy->format('Y-m-d'),
            'filas' => $this->obtenerAsistencia->execute($fecha, $request->string('q')->toString() ?: null, $request->filled('rol') ? $request->integer('rol') : null),
            'roles' => collect($this->roles->listarRemunerables())->map(fn ($rol) => ['id' => $rol->id(), 'nombre' => $rol->nombre()])->all(),
        ]);
    }
}
