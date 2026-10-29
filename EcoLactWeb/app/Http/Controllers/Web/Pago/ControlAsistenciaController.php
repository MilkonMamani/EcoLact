<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\MarcarFaltasAutomaticas;
use App\Application\Pago\ObtenerAsistenciaDelDia;
use App\Application\Pago\VentanaAsistencia;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Role;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Asistencia de hoy: quién marcó (o no) en un día dado, para el personal que
 * marca asistencia (roles.controla_asistencia). Autorización vía la misma
 * ability "pay" de ObligacionPagoPolicy que ya protege el resto del módulo
 * de pagos/jornadas (exclusivo de ADMINISTRADOR, Gate::before).
 *
 * Hoy antes de las 23:59 (VentanaAsistencia) la pantalla es operativa
 * (escanear QR, registrar, corregir); desde el cierre, y para cualquier fecha
 * anterior, es solo consulta. Antes de listar cierra los días ya cerrados
 * (MarcarFaltasAutomaticas, idempotente): después de las 23:59 nadie que
 * debía asistir queda "Pendiente" aunque el scheduler todavía no haya corrido.
 */
final class ControlAsistenciaController extends Controller
{
    private const POR_PAGINA = 20;

    public function __construct(
        private readonly ObtenerAsistenciaDelDia $obtenerAsistencia,
        private readonly RoleRepository $roles,
        private readonly MarcarFaltasAutomaticas $marcarFaltas,
        private readonly VentanaAsistencia $ventana,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('pay', ObligacionPagoModel::class);

        $this->marcarFaltas->execute();

        $hoy = $this->ventana->hoy();

        try {
            $fecha = $request->filled('fecha') ? new DateTimeImmutable((string) $request->string('fecha')) : $hoy;
        } catch (Throwable) {
            $fecha = $hoy;
        }

        if ($fecha > $hoy) {
            $fecha = $hoy;
        }

        return view('admin.asistencia.index', [
            'fecha' => $fecha,
            'esHoy' => $fecha->format('Y-m-d') === $hoy->format('Y-m-d'),
            'asistenciaAbierta' => $this->ventana->estaAbierta($fecha),
            'horaCierre' => VentanaAsistencia::horaCierre(),
            'fechaEtiqueta' => ucfirst(CarbonImmutable::instance($fecha)->locale('es')->translatedFormat('l, j \d\e F \d\e Y')),
            'datos' => $this->obtenerAsistencia->execute(
                $fecha,
                $request->string('q')->toString() ?: null,
                $request->filled('rol') ? $request->integer('rol') : null,
                self::POR_PAGINA,
            ),
            'roles' => collect($this->roles->listarRemunerables())
                ->filter(fn (Role $rol): bool => $rol->controlaAsistencia())
                ->map(fn (Role $rol): array => ['id' => $rol->id(), 'nombre' => $rol->etiqueta()])
                ->sortBy('nombre')
                ->values()
                ->all(),
        ]);
    }
}
