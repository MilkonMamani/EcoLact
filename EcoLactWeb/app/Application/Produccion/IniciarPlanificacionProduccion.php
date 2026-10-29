<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\PlanificacionProduccion;
use App\Domain\Produccion\PlanificacionProduccionRepository;
use DateTimeImmutable;
use DomainException;

/**
 * INICIAR UNA PLANIFICACIÓN desde "Nueva producción" (web): varios productos
 * en una sola operación, pero UN LOTE POR PRODUCTO. Cada lote guarda su
 * receta, cantidad planificada, consumos y movimientos, y se finaliza por
 * separado sin afectar a los demás.
 *
 * Todo ocurre en UNA transacción (o se crean todos los lotes o ninguno):
 *
 *   1. recalcula los requerimientos de TODOS los productos juntos desde sus
 *      recetas activas, bloqueando cada insumo (FOR UPDATE, en orden de id),
 *   2. valida el total agregado contra el stock: si algo falta o un producto
 *      no tiene receta, nada se crea y se dice exactamente qué falta,
 *   3. con dos o más productos, registra la planificación (PLN-00001...) que
 *      agrupa sus lotes; con uno solo, el lote queda como siempre,
 *   4. por cada producto crea su lote y descuenta SOLO su porción de insumos
 *      (la suma de porciones es exactamente lo validado en el paso 2).
 *
 * Un producto repetido se suma en una sola línea (CalcularRequerimientos), así
 * que nunca se crean dos lotes del mismo producto en una planificación.
 */
final class IniciarPlanificacionProduccion
{
    public function __construct(
        private readonly CalcularRequerimientosProduccion $calcular,
        private readonly RegistrarLoteProduccion $registrarLote,
        private readonly NotificarStockBajoInsumos $notificarStockBajo,
        private readonly PlanificacionProduccionRepository $planificaciones,
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
    ): PlanificacionProduccionIniciada {
        $observacion = $observacion !== null && trim($observacion) !== '' ? trim($observacion) : null;

        return $this->transacciones->run(function () use ($lineas, $registradoPorUserId, $observacion, $fechaHoraInicio): PlanificacionProduccionIniciada {
            $requerimientos = $this->calcular->execute($lineas, bloquearInsumos: true);

            if (! $requerimientos->puedeIniciar()) {
                throw new ProduccionBloqueadaException($requerimientos->mensajesDeBloqueo());
            }

            $planificacion = count($requerimientos->lineas) > 1 ? $this->registrarPlanificacion($registradoPorUserId, $observacion) : null;
            $inicio = $fechaHoraInicio ?? new DateTimeImmutable;
            $lotes = [];

            foreach ($requerimientos->lineas as $linea) {
                $lotes[] = $this->registrarLote->execute(
                    lineas: [$linea],
                    insumos: $linea['insumos'],
                    registradoPorUserId: $registradoPorUserId,
                    observacion: $observacion,
                    fechaHoraInicio: $inicio,
                    planificacionProduccionId: $planificacion?->id(),
                );
            }

            $this->notificarStockBajo->execute(array_map(static fn (RequerimientoInsumo $r): int => $r->insumoId, $requerimientos->insumos));

            return new PlanificacionProduccionIniciada($planificacion, $lotes);
        });
    }

    private function registrarPlanificacion(int $registradoPorUserId, ?string $observacion): PlanificacionProduccion
    {
        $planificacion = $this->planificaciones->guardar(new PlanificacionProduccion(
            id: null,
            codigo: 'PENDIENTE',
            registradoPorUserId: $registradoPorUserId,
            observacion: $observacion,
        ));

        $id = $planificacion->id() ?? throw new DomainException('No se pudo registrar la planificación de producción.');

        return $this->planificaciones->actualizar($planificacion->conCodigo(sprintf('PLN-%05d', $id)));
    }
}
