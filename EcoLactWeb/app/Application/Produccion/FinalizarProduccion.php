<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Domain\Produccion\ProduccionDetalleRepository;
use App\Domain\Produccion\ProductoMovimiento;
use App\Domain\Produccion\ProductoMovimientoRepository;
use DateTimeImmutable;
use DomainException;

/**
 * FINALIZAR PRODUCCIÓN: registra lo REALMENTE obtenido (no se asume que lo
 * planificado sea lo producido) y lo ingresa al inventario de productos
 * terminados.
 *
 * NO vuelve a descontar insumos: eso ya ocurrió al iniciar el lote. Solo:
 *   - valida que el lote esté EN_PROCESO (bloqueado FOR UPDATE, así dos
 *     personas no pueden finalizarlo a la vez ni ingresar el producto dos
 *     veces),
 *   - guarda la cantidad obtenida de cada producto,
 *   - genera un movimiento INGRESO_PRODUCCION por cada producto con cantidad
 *     obtenida mayor que cero (el saldo del producto es la suma de sus
 *     movimientos),
 *   - marca el lote FINALIZADO.
 */
final class FinalizarProduccion
{
    public function __construct(
        private readonly LoteProduccionRepository $lotes,
        private readonly ProduccionDetalleRepository $produccionDetalles,
        private readonly ProductoMovimientoRepository $productoMovimientos,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  array<int, float|int|string>  $cantidadesObtenidas  cantidad real obtenida, indexada por id del detalle de producción
     */
    public function execute(
        int $loteId,
        array $cantidadesObtenidas,
        int $registradoPorUserId,
        ?DateTimeImmutable $fechaHoraFin = null,
    ): LoteProduccion {
        return $this->transacciones->run(function () use ($loteId, $cantidadesObtenidas, $registradoPorUserId, $fechaHoraFin): LoteProduccion {
            $lote = $this->lotes->bloquearPorId($loteId)
                ?? throw new DomainException('El lote de producción indicado no existe.');

            if (! $lote->estaEnProceso()) {
                throw new DomainException('Este lote ya fue finalizado.');
            }

            $detalles = $this->produccionDetalles->listarPorLote($loteId);

            if ($detalles === []) {
                throw new DomainException('El lote no tiene productos planificados.');
            }

            $fin = $fechaHoraFin ?? new DateTimeImmutable;

            foreach ($detalles as $detalle) {
                $valor = $cantidadesObtenidas[$detalle->id()] ?? null;

                if ($valor === null || $valor === '' || ! is_numeric($valor)) {
                    throw new DomainException('Indique la cantidad realmente obtenida de cada producto (puede ser 0).');
                }

                $obtenida = round((float) $valor, 2);

                if ($obtenida < 0.0) {
                    throw new DomainException('La cantidad obtenida no puede ser negativa.');
                }

                $this->produccionDetalles->actualizar($detalle->conCantidadObtenida($obtenida));

                if ($obtenida > 0.0) {
                    $this->productoMovimientos->guardar(new ProductoMovimiento(
                        id: null,
                        productoId: $detalle->productoId(),
                        tipo: 'INGRESO_PRODUCCION',
                        cantidad: $obtenida,
                        loteProduccionId: $loteId,
                        ventaProductoId: null,
                        fecha: $fin,
                        registradoPorUserId: $registradoPorUserId,
                        observacion: sprintf('Producción del lote %s.', $lote->codigo()),
                    ));
                }
            }

            return $this->lotes->actualizar(new LoteProduccion(
                id: $lote->id(),
                codigo: $lote->codigo(),
                fechaHoraInicio: $lote->fechaHoraInicio(),
                fechaHoraFin: $fin,
                estado: LoteProduccion::ESTADO_FINALIZADO,
                registradoPorUserId: $lote->registradoPorUserId(),
                observacion: $lote->observacion(),
            ));
        });
    }
}
