<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\AjustarObligacionPersonal;
use App\Application\Pago\ComprobanteSubido;
use App\Application\Pago\Contracts\TrabajadoresPagoListadoQuery;
use App\Application\Pago\DiagnosticarSueldosDelPeriodo;
use App\Application\Pago\GenerarObligacionesPersonal;
use App\Application\Pago\MarcarFaltasAutomaticas;
use App\Application\Pago\ObtenerPagosTrabajadores;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Application\Pago\TrabajadoresPagoFiltro;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\MetodoPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pago\AjustarObligacionPersonalRequest;
use App\Http\Requests\Pago\RegistrarPagoRequest;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pagar a trabajadores: sueldo mensual del personal (generado desde
 * Configuración de pagos), con bonificaciones/descuentos ajustables por
 * trabajador mientras el pago esté PENDIENTE. Reutiliza el mismo
 * RegistrarPagoObligacion y el mismo comprobante subido que la pantalla
 * general de Pagos; solo cambia la presentación (tabla + panel lateral) y el
 * filtrado (aquí siempre es personal, nunca proveedores).
 */
final class PagoTrabajadorController extends Controller
{
    public function __construct(
        private readonly ObtenerPagosTrabajadores $obtenerPagos,
        private readonly TrabajadoresPagoListadoQuery $listado,
        private readonly AjustarObligacionPersonal $ajustarObligacion,
        private readonly RegistrarPagoObligacion $registrarPago,
        private readonly GenerarObligacionesPersonal $generarObligaciones,
        private readonly DiagnosticarSueldosDelPeriodo $diagnosticarSueldos,
        private readonly RoleRepository $roles,
        private readonly MarcarFaltasAutomaticas $marcarFaltas,
    ) {}

    /**
     * Antes de listar, sincroniza las obligaciones PENDIENTES del personal
     * (idempotente: la clave única beneficiario+periodo nunca duplica). Así
     * el administrador ve los sueldos correctos con solo entrar a la
     * pantalla, sin depender del cron nocturno (ecolact:generar-pagos) ni de
     * un botón manual — ver GenerarObligacionesPersonal.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ObligacionPagoModel::class);

        $this->generarObligaciones->execute(DateTimeImmutable::createFromInterface(now()));
        // Cierra los días de asistencia ya vencidos (07:00) antes de mostrar faltas y acumulados.
        $this->marcarFaltas->execute();

        $periodo = $request->string('periodo')->toString() ?: now()->format('Y-m');
        $fechaDelPeriodo = DateTimeImmutable::createFromFormat('Y-m-d', $periodo.'-01')->setTime(0, 0);

        $filtro = TrabajadoresPagoFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            rolId: $request->filled('rol') ? $request->integer('rol') : null,
            periodo: $periodo,
            estado: $request->string('estado')->toString() ?: null,
        );

        $filas = $this->obtenerPagos->execute($filtro);

        $hayFiltrosDeBusqueda = $filtro->busqueda !== null || $filtro->rolId !== null || $filtro->estado !== null;
        // Sin ningún pago generado (la tabla vacía o solo con trabajadores "sin sueldo"), se explica por qué.
        $hayPagos = collect($filas->items())->contains(fn ($fila): bool => ! $fila->sinSueldo());
        $diagnostico = (! $hayPagos && $filas->onFirstPage() && ! $hayFiltrosDeBusqueda)
            ? $this->diagnosticarSueldos->execute($fechaDelPeriodo)
            : null;

        // ?trabajador=ID de la obligación, o "s{trabajador}" para un trabajador cuyo rol no tiene sueldo configurado.
        $clave = $request->string('trabajador')->toString();
        $seleccion = match (true) {
            str_starts_with($clave, 's') && ctype_digit(substr($clave, 1)) => $this->listado->buscarSinSueldo((int) substr($clave, 1), $periodo),
            ctype_digit($clave) && $clave !== '0' => $this->listado->buscarFila((int) $clave),
            default => null,
        };
        $seleccion ??= collect($filas->items())->first(fn ($fila) => $fila->estaPendiente()) ?? collect($filas->items())->first();

        return view('admin.pagos.trabajadores.index', [
            'filas' => $filas,
            'filtro' => $filtro,
            'periodo' => $periodo,
            'periodoEtiqueta' => PeriodoPago::mesQueContiene($fechaDelPeriodo)->etiqueta(),
            'diagnostico' => $diagnostico,
            'roles' => collect($this->roles->listarRemunerables())->map(fn ($rol) => ['id' => $rol->id(), 'nombre' => $rol->etiqueta()])->sortBy('nombre')->values()->all(),
            'seleccion' => $seleccion,
            'metodos' => MetodoPago::opciones(),
            'metodosElectronicos' => array_values(array_map(
                fn (MetodoPago $metodo): string => $metodo->value,
                array_filter(MetodoPago::cases(), fn (MetodoPago $metodo): bool => $metodo->requiereNumeroOperacion()),
            )),
        ]);
    }

    /** Fragmento HTML (sin layout) del panel derecho para un trabajador: lo pide el JS al hacer clic en una fila. */
    public function panel(ObligacionPagoModel $obligacion): View
    {
        Gate::authorize('view', $obligacion);

        $fila = $this->listado->buscarFila($obligacion->id) ?? abort(404);

        return view('admin.pagos.trabajadores._panel', [
            'seleccion' => $fila,
            'metodos' => MetodoPago::opciones(),
            'metodosElectronicos' => array_values(array_map(
                fn (MetodoPago $metodo): string => $metodo->value,
                array_filter(MetodoPago::cases(), fn (MetodoPago $metodo): bool => $metodo->requiereNumeroOperacion()),
            )),
        ]);
    }

    /**
     * Fragmento del panel de un trabajador cuyo rol no tiene sueldo configurado:
     * no hay obligación que pagar, solo su asistencia y el aviso para configurar el sueldo.
     */
    public function panelSinSueldo(Request $request, TrabajadorModel $trabajador): View
    {
        Gate::authorize('viewAny', ObligacionPagoModel::class);

        $periodo = $request->string('periodo')->toString();
        $periodo = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1 ? $periodo : now()->format('Y-m');

        return view('admin.pagos.trabajadores._panel', [
            'seleccion' => $this->listado->buscarSinSueldo($trabajador->id, $periodo) ?? abort(404),
            'metodos' => [],
            'metodosElectronicos' => [],
        ]);
    }

    /** Fija bonificación/descuento de un trabajador pendiente y devuelve el nuevo resumen. */
    public function ajustar(AjustarObligacionPersonalRequest $request, ObligacionPagoModel $obligacion): JsonResponse
    {
        Gate::authorize('pay', $obligacion);

        $datos = $request->validated();

        try {
            $resultado = $this->ajustarObligacion->execute(
                obligacionId: $obligacion->id,
                bonificacion: (float) ($datos['bonificacion'] ?? 0),
                descuento: (float) ($datos['descuento'] ?? 0),
            );
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'sueldoBase' => $resultado->sueldoBase,
            'bonificacion' => $resultado->bonificacion,
            'descuento' => $resultado->descuento,
            'total' => $resultado->total,
        ]);
    }

    /**
     * Confirma el pago de un trabajador. Misma lógica que el registro general
     * de Pagos (RegistrarPagoObligacion); solo cambia a dónde vuelve tras
     * guardar, para quedarse en esta pantalla con el trabajador ya pagado
     * seleccionado.
     */
    public function registrarPago(RegistrarPagoRequest $request, ObligacionPagoModel $obligacion): RedirectResponse
    {
        Gate::authorize('pay', $obligacion);

        $datos = $request->validated();
        $archivo = $request->file('comprobante');

        try {
            $this->registrarPago->execute(
                obligacionId: $obligacion->id,
                metodoPago: MetodoPago::from($datos['metodo_pago']),
                numeroOperacion: $datos['numero_operacion'] ?? null,
                fechaPago: new DateTimeImmutable($datos['fecha_pago']),
                observacion: $datos['observacion'] ?? null,
                comprobante: $archivo !== null
                    ? new ComprobanteSubido($archivo->getRealPath(), $archivo->guessExtension() ?? $archivo->getClientOriginalExtension(), $archivo->getClientOriginalName())
                    : null,
                registradoPorUserId: $request->user()->id,
                montoDeclarado: isset($datos['monto']) ? (float) $datos['monto'] : null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['pago' => $exception->getMessage()]);
        }

        return redirect()->route('admin.pagos.trabajadores.index', $request->only(['q', 'rol', 'periodo', 'estado']) + ['trabajador' => $obligacion->id])
            ->with('success', 'Pago registrado correctamente.');
    }

    /** Comprobante individual imprimible de un trabajador (pendiente o ya pagado). */
    public function comprobante(ObligacionPagoModel $obligacion): View
    {
        Gate::authorize('view', $obligacion);

        $comprobante = $this->listado->comprobante($obligacion->id) ?? abort(404);

        return view('admin.pagos.trabajadores.comprobante', ['c' => $comprobante]);
    }
}
