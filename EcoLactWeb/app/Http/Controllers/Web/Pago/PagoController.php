<?php

namespace App\Http\Controllers\Web\Pago;

use App\Application\Pago\ComprobanteSubido;
use App\Application\Pago\Contracts\AlmacenComprobantePago;
use App\Application\Pago\GenerarPagosPendientes;
use App\Application\Pago\ObligacionesPagoFiltro;
use App\Application\Pago\ObtenerObligacionesPago;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Shared\Enums\MetodoPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pago\RegistrarPagoRequest;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagos: muestra los pagos pendientes y pagados generados desde
 * Configuración de pagos y permite confirmar la entrega del dinero. Aquí NO
 * se crean pagos ni beneficiarios a mano.
 */
final class PagoController extends Controller
{
    public function __construct(
        private readonly ObtenerObligacionesPago $obtenerObligaciones,
        private readonly RegistrarPagoObligacion $registrarPago,
        private readonly GenerarPagosPendientes $generarPagos,
        private readonly AlmacenComprobantePago $comprobantes,
        private readonly RoleRepository $roles,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ObligacionPagoModel::class);

        $filtro = ObligacionesPagoFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            tipo: $request->string('tipo')->toString() ?: null,
            periodo: $request->string('periodo')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
        );

        return view('admin.pagos.index', [
            'datos' => $this->obtenerObligaciones->execute($filtro),
            'tipos' => ['PROVEEDOR' => 'Proveedor'] + collect($this->roles->listarRemunerables())->mapWithKeys(fn ($rol) => [$rol->nombre() => $rol->nombre()])->all(),
            'metodos' => MetodoPago::opciones(),
            'metodosElectronicos' => array_values(array_map(
                fn (MetodoPago $metodo): string => $metodo->value,
                array_filter(MetodoPago::cases(), fn (MetodoPago $metodo): bool => $metodo->requiereNumeroOperacion()),
            )),
        ]);
    }

    public function registrar(RegistrarPagoRequest $request, ObligacionPagoModel $obligacion): RedirectResponse
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
                // Quien confirma el pago es siempre el usuario autenticado.
                registradoPorUserId: $request->user()->id,
                montoDeclarado: isset($datos['monto']) ? (float) $datos['monto'] : null,
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['pago' => $exception->getMessage()]);
        }

        return redirect()->route('admin.pagos.index', $request->only(['q', 'tipo', 'periodo', 'estado']))
            ->with('success', 'Pago registrado correctamente.');
    }

    public function comprobante(ObligacionPagoModel $obligacion): Response
    {
        Gate::authorize('view', $obligacion);

        $pago = $obligacion->pago;
        $ruta = $pago?->comprobante_path;

        abort_if($ruta === null || ! $this->comprobantes->existe($ruta), 404);

        return $this->comprobantes->responder($ruta, $pago->comprobante_nombre ?? basename($ruta));
    }

    /**
     * Ejecuta la generación idempotente de pendientes (la misma que corre el
     * scheduler). No permite elegir beneficiario ni monto.
     */
    public function generar(Request $request): RedirectResponse
    {
        Gate::authorize('generate', ObligacionPagoModel::class);

        $resultado = $this->generarPagos->execute(actorUserId: $request->user()->id);

        $mensaje = $resultado->creadas > 0
            ? "Se generaron {$resultado->creadas} pago(s) pendiente(s)."
            : 'No hay pagos nuevos por generar.';

        $redireccion = redirect()->route('admin.pagos.index')->with('success', $mensaje);

        if ($resultado->omitidas !== []) {
            $redireccion->with('warning', collect($resultado->omitidas)
                ->map(fn (array $omitida): string => "{$omitida['beneficiario']} ({$omitida['periodo']}): {$omitida['motivo']}")
                ->take(5)
                ->implode(' · '));
        }

        return $redireccion;
    }
}
