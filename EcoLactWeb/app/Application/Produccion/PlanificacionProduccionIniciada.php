<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\PlanificacionProduccion;

/**
 * Resultado de IniciarPlanificacionProduccion: los lotes creados (uno por
 * producto) y la planificación que los agrupa (null si fue un solo producto).
 */
final readonly class PlanificacionProduccionIniciada
{
    /**
     * @param  list<LoteProduccion>  $lotes
     */
    public function __construct(
        public ?PlanificacionProduccion $planificacion,
        public array $lotes,
    ) {}

    /**
     * "Producción LOT-00007 iniciada." o "Planificación PLN-00002 iniciada: 3 lotes (LOT-00007, LOT-00008 y LOT-00009)."
     */
    public function mensaje(): string
    {
        $codigos = array_map(static fn (LoteProduccion $lote): string => $lote->codigo(), $this->lotes);

        if ($this->planificacion === null || count($codigos) === 1) {
            return "Producción {$codigos[0]} iniciada. Los insumos fueron descontados.";
        }

        $ultimo = array_pop($codigos);

        return sprintf(
            'Planificación %s iniciada: %d lotes (%s y %s). Los insumos fueron descontados.',
            $this->planificacion->codigo(),
            count($this->lotes),
            implode(', ', $codigos),
            $ultimo,
        );
    }
}
