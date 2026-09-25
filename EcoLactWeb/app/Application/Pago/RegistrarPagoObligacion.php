<?php

namespace App\Application\Pago;

use App\Application\Liquidacion\RegistrarPago;
use App\Application\Pago\Contracts\AlmacenComprobantePago;
use App\Application\Shared\TransactionManager;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Pago\PagoRealizado;
use App\Domain\Pago\PagoRealizadoRepository;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * Confirma que el dinero de un pago PENDIENTE fue entregado. Solo una
 * obligación pendiente puede pagarse, una sola vez y por su monto calculado
 * exacto (no hay pagos parciales). Todo ocurre en una transacción: pago real,
 * comprobante, cambio de estado y usuario que lo registró.
 *
 * Para un proveedor se registra además el pago en el flujo de liquidaciones
 * (RegistrarPago), de modo que el portal y la app del proveedor sigan viendo
 * su liquidación como pagada.
 */
final class RegistrarPagoObligacion
{
    public function __construct(
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly PagoRealizadoRepository $pagos,
        private readonly RegistrarPago $registrarPagoLiquidacion,
        private readonly AlmacenComprobantePago $comprobantes,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $obligacionId,
        MetodoPago $metodoPago,
        ?string $numeroOperacion,
        DateTimeImmutable $fechaPago,
        ?string $observacion,
        ?ComprobanteSubido $comprobante,
        int $registradoPorUserId,
        ?float $montoDeclarado = null,
        ?DateTimeImmutable $hoy = null,
        ?string $loteCodigo = null,
        ?DateTimeImmutable $impresoAt = null,
        ?array $comprobanteSnapshot = null,
    ): PagoRealizado {
        $hoy = ($hoy ?? DateTimeImmutable::createFromInterface(now()))->setTime(0, 0);
        $fechaPago = $fechaPago->setTime(0, 0);
        $numeroOperacion = $numeroOperacion !== null && trim($numeroOperacion) !== '' ? trim($numeroOperacion) : null;
        $observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;

        if ($metodoPago->requiereNumeroOperacion() && $numeroOperacion === null) {
            throw new DomainException('El número de operación es obligatorio para '.$metodoPago->etiqueta().'.');
        }

        if ($fechaPago > $hoy) {
            throw new DomainException('La fecha de pago no puede ser posterior a hoy.');
        }

        $rutaComprobante = $comprobante !== null
            ? $this->comprobantes->guardar($comprobante->rutaTemporal, $comprobante->extension)
            : null;

        try {
            return $this->transacciones->run(function () use (
                $obligacionId, $metodoPago, $numeroOperacion, $fechaPago, $observacion,
                $comprobante, $rutaComprobante, $registradoPorUserId, $montoDeclarado,
                $loteCodigo, $impresoAt, $comprobanteSnapshot,
            ): PagoRealizado {
                $obligacion = $this->obligaciones->buscarPorIdBloqueando($obligacionId)
                    ?? throw new DomainException('El pago pendiente indicado no existe.');

                if (! $obligacion->estaPendiente()) {
                    throw new DomainException('Este pago ya fue registrado. No se puede pagar dos veces.');
                }

                if ($fechaPago < $obligacion->periodoInicio()) {
                    throw new DomainException('La fecha de pago no puede ser anterior al inicio del periodo que se paga.');
                }

                if ($montoDeclarado !== null && round($montoDeclarado, 2) !== round($obligacion->montoCalculado(), 2)) {
                    throw new DomainException('El monto a pagar debe ser exactamente el monto calculado. No se admiten pagos parciales.');
                }

                // Congela días trabajados/valor por día/acumulado del personal al
                // momento de pagar, para que un cambio de sueldo después no altere
                // lo ya pagado. El flujo de proveedores ya pasa su propio snapshot.
                $comprobanteSnapshot ??= $obligacion->tipoBeneficiario() === TipoBeneficiarioPago::PERSONAL
                    ? [
                        'dias_trabajados' => $obligacion->diasTrabajados(),
                        'valor_dia' => $obligacion->valorDia(),
                        'sueldo_mensual' => $obligacion->baseCalculo()['sueldo_base'] ?? null,
                        'acumulado' => $obligacion->acumulado(),
                    ]
                    : null;

                $pagoProveedorId = null;

                if ($obligacion->liquidacionId() !== null) {
                    $pagoProveedorId = $this->registrarPagoLiquidacion->execute(
                        liquidacionId: $obligacion->liquidacionId(),
                        monto: $obligacion->montoCalculado(),
                        registradoPorUserId: $registradoPorUserId,
                        fechaPago: $fechaPago->setTime((int) date('H'), (int) date('i'), (int) date('s')),
                        observacion: $observacion,
                    )->id();
                }

                try {
                    $pago = $this->pagos->guardar(new PagoRealizado(
                        id: null,
                        obligacionPagoId: $obligacionId,
                        fechaPago: $fechaPago,
                        monto: $obligacion->montoCalculado(),
                        metodoPago: $metodoPago,
                        numeroOperacion: $numeroOperacion,
                        observacion: $observacion,
                        comprobantePath: $rutaComprobante,
                        comprobanteNombre: $comprobante?->nombreOriginal,
                        registradoPorUserId: $registradoPorUserId,
                        pagoProveedorId: $pagoProveedorId,
                        pagadoAt: DateTimeImmutable::createFromInterface(now()),
                        impresoAt: $impresoAt,
                        loteCodigo: $loteCodigo,
                        comprobanteSnapshot: $comprobanteSnapshot,
                    ));
                } catch (InvalidArgumentException $e) {
                    throw new DomainException($e->getMessage());
                }

                $this->obligaciones->marcarComoPagada($obligacionId);

                return $pago;
            });
        } catch (Throwable $e) {
            $this->comprobantes->eliminar($rutaComprobante);

            throw $e;
        }
    }
}
