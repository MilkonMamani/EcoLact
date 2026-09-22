<?php

namespace App\Application\Liquidacion;

use App\Application\Shared\TransactionManager;
use App\Domain\Comunicacion\Notificacion;
use App\Domain\Comunicacion\Repositories\NotificacionRepository;
use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Pago\PagoProveedor;
use App\Domain\Pago\PagoProveedorRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoLiquidacion;
use App\Domain\Shared\Enums\EstadoPago;
use DateTimeImmutable;
use DomainException;

/**
 * Registra un pago contra una liquidación, validando que exista, que no
 * esté ya completamente pagada y que el monto no exceda el saldo
 * pendiente. El estado de la liquidación se recalcula a partir del saldo
 * restante: PARCIALMENTE_PAGADA mientras quede saldo > 0 tras un pago
 * activo, o PAGADA cuando el saldo llega a 0. EstadoPago no tiene un caso
 * "parcial": lo parcial es una propiedad del estado global de la
 * liquidación, no de cada operación de pago individual.
 *
 * El pago al proveedor SIEMPRE es en efectivo, entregado en sobre cerrado:
 * no existe otro método ni número de operación (eso es de transferencias),
 * por eso ninguno de los dos es un dato de entrada aquí.
 */
final class RegistrarPago
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly PagoProveedorRepository $pagos,
        private readonly CalcularPago $calcularPago,
        private readonly ProveedorRepository $proveedores,
        private readonly NotificacionRepository $notificaciones,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $liquidacionId,
        float $monto,
        int $registradoPorUserId,
        ?DateTimeImmutable $fechaPago = null,
        ?string $observacion = null,
    ): PagoProveedor {
        $liquidacion = $this->liquidaciones->buscarPorId($liquidacionId);

        if ($liquidacion === null) {
            throw new DomainException('La liquidación indicada no existe.');
        }

        if ($liquidacion->estado() === EstadoLiquidacion::ANULADA) {
            throw new DomainException('No se puede registrar un pago sobre una liquidación anulada.');
        }

        $saldoPendiente = $this->calcularPago->execute($liquidacionId);

        if ($saldoPendiente <= 0.0) {
            throw new DomainException('La liquidación ya fue pagada completamente.');
        }

        if (round($monto, 2) > $saldoPendiente) {
            throw new DomainException('El monto del pago supera el saldo pendiente de la liquidación.');
        }

        return $this->transacciones->run(function () use (
            $liquidacion, $monto, $registradoPorUserId,
            $fechaPago, $observacion, $saldoPendiente,
        ): PagoProveedor {
            $pago = $this->pagos->guardar(new PagoProveedor(
                id: null,
                liquidacionId: $liquidacion->id(),
                fechaPago: $fechaPago ?? new DateTimeImmutable,
                monto: $monto,
                estado: EstadoPago::REGISTRADO,
                registradoPorUserId: $registradoPorUserId,
                observacion: $observacion,
            ));

            $nuevoEstado = round($saldoPendiente - $monto, 2) <= 0.0
                ? EstadoLiquidacion::PAGADA
                : EstadoLiquidacion::PARCIALMENTE_PAGADA;

            if ($nuevoEstado !== $liquidacion->estado()) {
                $liquidacion = $this->liquidaciones->actualizar(new Liquidacion(
                    id: $liquidacion->id(),
                    periodoId: $liquidacion->periodoId(),
                    proveedorId: $liquidacion->proveedorId(),
                    totalLitrosEntregados: $liquidacion->totalLitrosEntregados(),
                    totalLitrosPagables: $liquidacion->totalLitrosPagables(),
                    subtotal: $liquidacion->subtotal(),
                    totalAjustes: $liquidacion->totalAjustes(),
                    totalPagar: $liquidacion->totalPagar(),
                    estado: $nuevoEstado,
                    fechaCalculo: $liquidacion->fechaCalculo(),
                    generadoPorUserId: $liquidacion->generadoPorUserId(),
                    precioLitroAplicado: $liquidacion->precioLitroAplicado(),
                    tuvoIncidencia: $liquidacion->tuvoIncidencia(),
                    sancionId: $liquidacion->sancionId(),
                ));
            }

            $proveedor = $this->proveedores->buscarPorId($liquidacion->proveedorId());

            if ($proveedor !== null) {
                $this->notificaciones->guardar(new Notificacion(
                    id: null,
                    userId: $proveedor->userId(),
                    tipo: 'PAGO_REALIZADO',
                    titulo: 'Pago registrado',
                    mensaje: sprintf('Se registró un pago de S/ %.2f para tu liquidación.', $monto),
                    referenciaTipo: 'PAGO',
                    referenciaId: $pago->id(),
                    leido: false,
                ));
            }

            return $pago;
        });
    }
}
