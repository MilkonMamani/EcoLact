<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\LoteProduccion;
use DateTimeImmutable;

/**
 * INICIAR PRODUCCIÓN en UN solo lote (todas las líneas en el mismo lote).
 * Lo usa la API móvil; la web planifica con IniciarPlanificacionProduccion,
 * que crea un lote por producto. Es el único momento en que se descuentan
 * insumos.
 *
 * Todo ocurre en UNA transacción; si cualquier paso falla se deshace por
 * completo (ni lote, ni detalles, ni consumos, ni descuentos):
 *
 *   1. recalcula los requerimientos desde las recetas (no confía en lo que
 *      mostró la pantalla),
 *   2. vuelve a leer el stock bloqueando cada insumo (FOR UPDATE),
 *   3. verifica que haya receta para cada producto y stock suficiente,
 *   4. crea el lote EN_PROCESO con sus productos, consumos y movimientos
 *      CONSUMO_PRODUCCION (RegistrarLoteProduccion).
 *
 * Los productos terminados NO se ingresan aquí: eso ocurre al finalizar el
 * lote con lo realmente obtenido (FinalizarProduccion).
 */
final class IniciarProduccion
{
    public function __construct(
        private readonly CalcularRequerimientosProduccion $calcular,
        private readonly RegistrarLoteProduccion $registrarLote,
        private readonly NotificarStockBajoInsumos $notificarStockBajo,
        private readonly TransactionManager $transacciones,
    ) {}

    /**
     * @param  list<array{producto_id: int|string, cantidad: float|int|string}>  $lineas
     *
     * @throws ProduccionBloqueadaException si falta una receta o no alcanza un insumo.
     */
    public function execute(
        array $lineas,
        int $registradoPorUserId,
        ?string $observacion = null,
        ?DateTimeImmutable $fechaHoraInicio = null,
    ): LoteProduccion {
        return $this->transacciones->run(function () use ($lineas, $registradoPorUserId, $observacion, $fechaHoraInicio): LoteProduccion {
            $requerimientos = $this->calcular->execute($lineas, bloquearInsumos: true);

            if (! $requerimientos->puedeIniciar()) {
                throw new ProduccionBloqueadaException($requerimientos->mensajesDeBloqueo());
            }

            $lote = $this->registrarLote->execute(
                lineas: $requerimientos->lineas,
                insumos: array_map(static fn (RequerimientoInsumo $r): array => [
                    'insumoId' => $r->insumoId,
                    'nombre' => $r->nombre,
                    'unidadBase' => $r->unidadBase,
                    'necesario' => $r->necesario,
                ], $requerimientos->insumos),
                registradoPorUserId: $registradoPorUserId,
                observacion: $observacion,
                fechaHoraInicio: $fechaHoraInicio ?? new DateTimeImmutable,
            );

            $this->notificarStockBajo->execute(array_map(static fn (RequerimientoInsumo $r): int => $r->insumoId, $requerimientos->insumos));

            return $lote;
        });
    }
}
