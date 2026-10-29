<?php

namespace App\Application\Compra;

use App\Application\Produccion\RegistrarMovimientoInsumo;
use App\Application\Shared\TransactionManager;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Shared\Enums\EstadoRecepcionCompra;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DateTimeImmutable;
use DomainException;

/**
 * CONFIRMAR LA RECEPCIÓN de una compra: el ÚNICO momento en que una compra
 * aumenta el stock. Lo usa RegistrarCompra cuando los artículos ya llegaron
 * y el detalle de la compra cuando llegan después.
 *
 * En una transacción, con la compra bloqueada: por cada línea INVENTARIO que
 * todavía no tiene movimiento registra un INGRESO_COMPRA (origen
 * COMPRA_INSUMO #compra) con RegistrarMovimientoInsumo y lo enlaza a la
 * línea. Una compra ya recibida se rechaza: el stock entra una sola vez.
 */
final class RecibirCompra
{
    public const ACCION = 'COMPRA_RECIBIDA';

    public function __construct(
        private readonly CompraInsumoRepository $compras,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
        private readonly TransactionManager $transacciones,
        private readonly AuditoriaRepository $auditorias,
    ) {}

    /**
     * @return int cuántas líneas ingresaron al stock
     *
     * @throws DomainException si la compra no está pendiente de recibir.
     */
    public function execute(int $compraId, int $recibidaPorUserId): int
    {
        return $this->transacciones->run(function () use ($compraId, $recibidaPorUserId): int {
            $compra = $this->compras->buscarParaActualizar($compraId) ?? throw new DomainException('La compra no existe.');

            if ($compra->estadoRecepcion() !== EstadoRecepcionCompra::PENDIENTE) {
                throw new DomainException($compra->estadoRecepcion() === EstadoRecepcionCompra::RECIBIDA
                    ? "La compra {$compra->codigo()} ya fue recibida: su stock ya ingresó."
                    : "La compra {$compra->codigo()} no tiene artículos de inventario por recibir.");
            }

            $ingresadas = $this->ingresarStock($compra, $recibidaPorUserId);
            $this->compras->actualizarRecepcion($compraId, EstadoRecepcionCompra::RECIBIDA, DateTimeImmutable::createFromInterface(now()), $recibidaPorUserId);

            $this->auditorias->registrar(
                userId: $recibidaPorUserId,
                accion: self::ACCION,
                tablaAfectada: 'compras_insumo',
                registroId: $compraId,
                datosAnteriores: ['estado_recepcion' => EstadoRecepcionCompra::PENDIENTE->value],
                datosNuevos: ['estado_recepcion' => EstadoRecepcionCompra::RECIBIDA->value, 'lineas_ingresadas' => $ingresadas],
                motivo: null,
            );

            return $ingresadas;
        });
    }

    private function ingresarStock(CompraInsumo $compra, int $userId): int
    {
        $ingresadas = 0;

        foreach ($this->compras->listarDetalles((int) $compra->id()) as $detalle) {
            if (! $detalle->generaStock() || $detalle->insumoMovimientoId() !== null) {
                continue;
            }

            $movimiento = $this->registrarMovimiento->execute(
                insumoId: (int) $detalle->insumoId(),
                motivo: MotivoMovimientoInsumo::INGRESO_COMPRA,
                direccion: MotivoMovimientoInsumo::ENTRADA,
                cantidad: (float) $detalle->cantidadBase(),
                registradoPorUserId: $userId,
                observacion: $compra->referencia().' · '.$detalle->descripcionPresentacion(),
                origenTipo: InsumoMovimiento::ORIGEN_COMPRA_INSUMO,
                origenId: $compra->id(),
            );

            $this->compras->enlazarMovimientoDetalle((int) $detalle->id(), (int) $movimiento->id());
            $ingresadas++;
        }

        return $ingresadas;
    }
}
