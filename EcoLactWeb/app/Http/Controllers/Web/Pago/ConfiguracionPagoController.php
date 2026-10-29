<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\ActualizarConfiguracionPago;
use App\Application\Pago\Contracts\SueldosPorRolQuery;
use App\Application\Pago\ObtenerConfiguracionesPago;
use App\Application\Pago\RegistrarConfiguracionPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pago\StoreConfiguracionPagoRequest;
use App\Http\Requests\Pago\UpdateConfiguracionPagoRequest;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Configuración de pagos: define los VALORES BASE que usa Pagos, en dos pestañas.
 *
 * - Trabajadores: el sueldo base mensual de cada ROL laboral, que heredan todos sus
 *   trabajadores (indicadores + tabla + panel lateral; ?rol=ID deja uno seleccionado
 *   y el panel se pide por fetch).
 * - Proveedores: la configuración general (precio por litro y descuento por la
 *   semana OBSERVADA).
 *
 * NO registra dinero, ni asistencia, ni pagos; solo guarda vigencias con historial.
 */
final class ConfiguracionPagoController extends Controller
{
    private const PESTANAS = ['trabajadores', 'proveedores'];

    public function __construct(
        private readonly ObtenerConfiguracionesPago $obtenerConfiguraciones,
        private readonly SueldosPorRolQuery $sueldos,
        private readonly RegistrarConfiguracionPago $registrarConfiguracion,
        private readonly ActualizarConfiguracionPago $actualizarConfiguracion,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ConfiguracionPagoModel::class);

        $hoy = DateTimeImmutable::createFromInterface(today());
        $pestana = in_array($request->query('tab'), self::PESTANAS, true) ? $request->query('tab') : 'trabajadores';

        return view('admin.configuracion-pagos.index', [
            'datos' => $this->obtenerConfiguraciones->execute($hoy),
            'pestana' => $pestana,
            'rolSeleccionado' => $request->filled('rol') ? $this->sueldos->detalle($request->integer('rol'), $hoy) : null,
        ]);
    }

    /** Fragmento HTML del panel de un rol (lo pide admin-fichas.js al elegir una fila). */
    public function panelRol(int $rolId): View
    {
        Gate::authorize('viewAny', ConfiguracionPagoModel::class);

        return view('admin.configuracion-pagos._panel-rol', [
            'rol' => $this->sueldos->detalle($rolId, DateTimeImmutable::createFromInterface(today())),
        ]);
    }

    public function store(StoreConfiguracionPagoRequest $request): RedirectResponse
    {
        Gate::authorize('create', ConfiguracionPagoModel::class);

        $datos = $request->validated();
        $tipo = TipoBeneficiarioPago::from($datos['tipo_beneficiario']);

        try {
            $configuracion = $this->registrarConfiguracion->execute(
                tipo: $tipo,
                rolId: isset($datos['rol_id']) ? (int) $datos['rol_id'] : null,
                montoBase: isset($datos['monto_base']) ? (float) $datos['monto_base'] : null,
                aplicaCalidad: true,
                fechaInicio: new DateTimeImmutable($datos['fecha_inicio']),
                observacion: $datos['observacion'] ?? null,
                // El actor es siempre el usuario autenticado.
                creadoPorUserId: $request->user()->id,
                precioBaseLitro: isset($datos['precio_base_litro']) ? (float) $datos['precio_base_litro'] : null,
                ajustes: $this->ajustes($datos),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['configuracion' => $exception->getMessage()]);
        }

        return $this->volver($configuracion->tipoBeneficiario(), $configuracion->rolId())
            ->with('success', $tipo === TipoBeneficiarioPago::PERSONAL ? 'Sueldo del rol configurado. Todo su personal lo hereda.' : 'Configuración de proveedores guardada correctamente.');
    }

    public function update(UpdateConfiguracionPagoRequest $request, ConfiguracionPagoModel $configuracion): RedirectResponse
    {
        Gate::authorize('update', $configuracion);

        $datos = $request->validated();

        try {
            $actualizada = $this->actualizarConfiguracion->execute(
                configuracionId: $configuracion->id,
                montoBase: isset($datos['monto_base']) ? (float) $datos['monto_base'] : null,
                aplicaCalidad: true,
                fechaInicio: new DateTimeImmutable($datos['fecha_inicio']),
                observacion: $datos['observacion'] ?? null,
                actorUserId: $request->user()->id,
                precioBaseLitro: isset($datos['precio_base_litro']) ? (float) $datos['precio_base_litro'] : null,
                ajustes: $this->ajustes($datos),
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['configuracion' => $exception->getMessage()]);
        }

        return $this->volver($actualizada->tipoBeneficiario(), $actualizada->rolId())
            ->with('success', 'Configuración actualizada. Los pagos ya generados conservan su monto.');
    }

    /**
     * El descuento de la semana OBSERVADA llega en positivo (S/ por litro) y se
     * guarda como ajuste negativo. Sin descuento enviado se conserva el vigente.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, array{tipo_ajuste: string, valor_ajuste: float}>|null
     */
    private function ajustes(array $datos): ?array
    {
        if (! isset($datos['descuento_observada'])) {
            return null;
        }

        return ['OBSERVADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => -abs((float) $datos['descuento_observada'])]];
    }

    private function volver(TipoBeneficiarioPago $tipo, ?int $rolId): RedirectResponse
    {
        return redirect()->route('admin.configuracion-pagos.index', $tipo === TipoBeneficiarioPago::PERSONAL
            ? ['tab' => 'trabajadores', 'rol' => $rolId]
            : ['tab' => 'proveedores']);
    }
}
