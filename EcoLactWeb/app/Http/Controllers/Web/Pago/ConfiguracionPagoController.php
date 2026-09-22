<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\ActualizarConfiguracionPago;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Configuración de pagos: define cuánto y cómo se paga (precio base por litro y
 * ajustes por calidad del proveedor; sueldo mensual por rol del personal). NO
 * registra dinero ni crea pagos; solo guarda reglas vigentes con historial.
 */
final class ConfiguracionPagoController extends Controller
{
    public function __construct(
        private readonly ObtenerConfiguracionesPago $obtenerConfiguraciones,
        private readonly RegistrarConfiguracionPago $registrarConfiguracion,
        private readonly ActualizarConfiguracionPago $actualizarConfiguracion,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ConfiguracionPagoModel::class);

        return view('admin.configuracion-pagos.index', [
            'datos' => $this->obtenerConfiguraciones->execute(DateTimeImmutable::createFromInterface(today())),
        ]);
    }

    public function store(StoreConfiguracionPagoRequest $request): RedirectResponse
    {
        Gate::authorize('create', ConfiguracionPagoModel::class);

        $datos = $request->validated();

        try {
            $this->registrarConfiguracion->execute(
                tipo: TipoBeneficiarioPago::from($datos['tipo_beneficiario']),
                rolId: isset($datos['rol_id']) ? (int) $datos['rol_id'] : null,
                montoBase: isset($datos['monto_base']) ? (float) $datos['monto_base'] : null,
                aplicaCalidad: (bool) ($datos['aplica_calidad'] ?? false),
                fechaInicio: new DateTimeImmutable($datos['fecha_inicio']),
                observacion: $datos['observacion'] ?? null,
                // El actor es siempre el usuario autenticado.
                creadoPorUserId: $request->user()->id,
                precioBaseLitro: isset($datos['precio_base_litro']) ? (float) $datos['precio_base_litro'] : null,
                ajustes: $datos['ajustes'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['configuracion' => $exception->getMessage()]);
        }

        return redirect()->route('admin.configuracion-pagos.index')->with('success', 'Configuración de pago guardada correctamente.');
    }

    public function update(UpdateConfiguracionPagoRequest $request, ConfiguracionPagoModel $configuracion): RedirectResponse
    {
        Gate::authorize('update', $configuracion);

        $datos = $request->validated();

        try {
            $this->actualizarConfiguracion->execute(
                configuracionId: $configuracion->id,
                montoBase: isset($datos['monto_base']) ? (float) $datos['monto_base'] : null,
                aplicaCalidad: (bool) ($datos['aplica_calidad'] ?? false),
                fechaInicio: new DateTimeImmutable($datos['fecha_inicio']),
                observacion: $datos['observacion'] ?? null,
                actorUserId: $request->user()->id,
                precioBaseLitro: isset($datos['precio_base_litro']) ? (float) $datos['precio_base_litro'] : null,
                ajustes: $datos['ajustes'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['configuracion' => $exception->getMessage()]);
        }

        return redirect()->route('admin.configuracion-pagos.index')->with('success', 'Configuración de pago actualizada. Los pagos ya generados conservan su monto.');
    }
}
