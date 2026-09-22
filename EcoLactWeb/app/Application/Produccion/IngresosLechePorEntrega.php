<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DomainException;

/**
 * Consulta el rastro de leche que una entrega dejó en el inventario: cuánto
 * ingresó (INGRESO_ACOPIO) y cuánto se revirtió después (REVERSO), siempre
 * mediante movimientos con origen ENTREGA. Nunca se borra un movimiento: el
 * neto vigente es ingresos - reversos.
 */
final class IngresosLechePorEntrega
{
    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly InsumoMovimientoRepository $movimientos,
    ) {}

    public function leche(): Insumo
    {
        return $this->insumos->buscarPorCodigo(Insumo::CODIGO_LECHE)
            ?? throw new DomainException('El insumo del sistema Leche no está configurado.');
    }

    /**
     * @return list<InsumoMovimiento>
     */
    public function movimientosDe(int $entregaId): array
    {
        return array_values(array_filter(
            $this->movimientos->listarPorOrigen(InsumoMovimiento::ORIGEN_ENTREGA, $entregaId),
            static fn (InsumoMovimiento $movimiento): bool => in_array(
                $movimiento->motivo(),
                [MotivoMovimientoInsumo::INGRESO_ACOPIO->value, MotivoMovimientoInsumo::REVERSO->value],
                true,
            ),
        ));
    }

    public function tuvoIngreso(int $entregaId): bool
    {
        foreach ($this->movimientosDe($entregaId) as $movimiento) {
            if ($movimiento->motivo() === MotivoMovimientoInsumo::INGRESO_ACOPIO->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Litros (en la unidad base de la leche) que la entrega aporta hoy al stock.
     */
    public function neto(int $entregaId): float
    {
        return round(array_sum(array_map(
            static fn (InsumoMovimiento $movimiento): float => $movimiento->cantidadConSigno(),
            $this->movimientosDe($entregaId),
        )), 4);
    }
}
