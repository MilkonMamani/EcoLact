<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\LoteProduccion;

/**
 * Detalle de un lote: productos planificados y obtenidos, insumos consumidos
 * (fotografía histórica) y la leche utilizada, derivada del consumo real del
 * insumo LECHE registrado en el lote (no es un dato manual).
 *
 * @phpstan-type Producto array{detalleId: int, productoId: int, productoNombre: string, unidadMedida: string, cantidadPlanificada: float, cantidadObtenida: float|null, recetaId: int|null, recetaVersion: int|null}
 * @phpstan-type InsumoUsado array{insumoId: int, insumoNombre: string, unidadBase: string, cantidadRequerida: float, cantidadUsada: float, esLeche: bool}
 */
final readonly class LoteProduccionDetalleData
{
    /**
     * @param  list<Producto>  $productos
     * @param  list<InsumoUsado>  $insumosUtilizados
     */
    public function __construct(
        public LoteProduccion $lote,
        public array $productos,
        public array $insumosUtilizados,
        public float $lecheUtilizada,
    ) {}

    public function estaEnProceso(): bool
    {
        return $this->lote->estaEnProceso();
    }
}
