<?php

namespace App\Application\Compra;

use App\Application\Caja\RegistrarMovimientoCaja;
use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Compra\CategoriaCompra;
use App\Domain\Compra\CategoriaCompraRepository;
use App\Domain\Compra\PagoCompra;
use App\Domain\Compra\PagoCompraRepository;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Shared\Enums\MetodoPago;
use DateTimeImmutable;
use DomainException;

/**
 * PAGAR (total o parcialmente) una compra: el ÚNICO camino por el que el
 * dinero de Compras y gastos llega a Caja. En una transacción:
 *
 *   1. bloquea la compra (dos pagos simultáneos se ejecutan en fila),
 *   2. calcula el saldo con la suma REAL de pagos_compra, no con la pantalla,
 *   3. rechaza pagar una compra sin importe, ya pagada, o más que su saldo,
 *   4. guarda el pago y su ÚNICA salida de caja (PAGO_COMPRA #pago),
 *   5. enlaza el pago con su movimiento y actualiza lo pagado y el estado.
 *
 * Una compra de S/ 1,000 pagada 400 y luego 600 deja dos pagos y dos
 * salidas; nunca una por lo que sigue pendiente.
 */
final class RegistrarPagoCompra
{
    public const ACCION = 'PAGO_COMPRA_REGISTRADO';

    public function __construct(
        private readonly CompraInsumoRepository $compras,
        private readonly PagoCompraRepository $pagos,
        private readonly CategoriaCompraRepository $categorias,
        private readonly RegistrarMovimientoCaja $caja,
        private readonly TransactionManager $transacciones,
        private readonly AuditoriaRepository $auditorias,
    ) {}

    /**
     * @throws DomainException si el pago no es válido (no se registra nada).
     */
    public function execute(
        int $compraId,
        float $monto,
        ?MetodoPago $metodoPago,
        ?string $numeroOperacion,
        ?string $observacion,
        int $registradoPorUserId,
        ?DateTimeImmutable $fecha = null,
    ): PagoCompra {
        $monto = round($monto, 2);
        $numeroOperacion = $numeroOperacion !== null && trim($numeroOperacion) !== '' ? trim($numeroOperacion) : null;
        $observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;

        return $this->transacciones->run(function () use ($compraId, $monto, $metodoPago, $numeroOperacion, $observacion, $registradoPorUserId, $fecha): PagoCompra {
            $compra = $this->compras->buscarParaActualizar($compraId) ?? throw new DomainException('La compra no existe.');

            if ($compra->total() === null || $compra->total() <= 0.0) {
                throw new DomainException('Esta compra no tiene importe registrado: no se puede pagar.');
            }

            $pagado = $this->pagos->totalPagado($compraId);
            $saldo = round($compra->total() - $pagado, 2);

            if ($saldo <= 0.0) {
                throw new DomainException("La compra {$compra->codigo()} ya está pagada.");
            }
            if ($monto <= 0.0) {
                throw new DomainException('El monto del pago debe ser mayor que cero.');
            }
            if ($monto > $saldo) {
                throw new DomainException('El pago de S/ '.number_format($monto, 2).' supera el saldo pendiente de S/ '.number_format($saldo, 2).'.');
            }
            if ($metodoPago?->requiereNumeroOperacion() && $numeroOperacion === null) {
                throw new DomainException("Indica el número de operación del pago por {$metodoPago->etiqueta()}.");
            }

            $categoria = ($compra->categoriaCompraId() !== null ? $this->categorias->buscarPorId($compra->categoriaCompraId()) : null)
                ?? $this->categorias->buscarPorCodigo(CategoriaCompra::INSUMOS_PRODUCCION)
                ?? throw new DomainException('La compra no tiene un tipo de adquisición válido.');

            $pago = $this->pagos->guardar(new PagoCompra(
                id: null,
                compraInsumoId: $compraId,
                monto: $monto,
                metodoPago: $metodoPago,
                fecha: $fecha ?? DateTimeImmutable::createFromInterface(now()),
                registradoPorUserId: $registradoPorUserId,
                numeroOperacion: $numeroOperacion,
                observacion: $observacion,
            ));

            $movimiento = $this->caja->porPagoCompra($compra, $pago, $categoria);
            $this->pagos->enlazarMovimientoCaja((int) $pago->id(), (int) $movimiento->id());
            $this->compras->actualizarPagos($compra, $pagado + $monto);

            $this->auditorias->registrar(
                userId: $registradoPorUserId,
                accion: self::ACCION,
                tablaAfectada: 'pagos_compra',
                registroId: $pago->id(),
                datosAnteriores: ['compra' => $compra->codigo(), 'pagado' => $pagado, 'saldo' => $saldo],
                datosNuevos: ['monto' => $monto, 'metodo_pago' => $metodoPago?->value, 'movimiento_caja_id' => $movimiento->id(), 'saldo' => round($saldo - $monto, 2)],
                motivo: null,
            );

            return new PagoCompra(
                id: $pago->id(),
                compraInsumoId: $pago->compraInsumoId(),
                monto: $pago->monto(),
                metodoPago: $pago->metodoPago(),
                fecha: $pago->fecha(),
                registradoPorUserId: $pago->registradoPorUserId(),
                numeroOperacion: $pago->numeroOperacion(),
                observacion: $pago->observacion(),
                movimientoCajaId: $movimiento->id(),
            );
        });
    }
}
