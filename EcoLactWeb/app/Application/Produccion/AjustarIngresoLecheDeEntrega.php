<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DomainException;

/**
 * Mantiene el inventario de leche coherente cuando se corrigen los litros de
 * una entrega que ya ingresó leche. Registra solo la diferencia: un
 * INGRESO_ACOPIO si aumentaron los litros, o un REVERSO si disminuyeron (sin
 * tocar los movimientos anteriores). Si la entrega nunca ingresó leche, no
 * hace nada.
 */
final class AjustarIngresoLecheDeEntrega
{
    public function __construct(
        private readonly IngresosLechePorEntrega $ingresos,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
    ) {}

    public function execute(int $entregaId, float $nuevosLitros, int $registradoPorUserId): ?InsumoMovimiento
    {
        if (! $this->ingresos->tuvoIngreso($entregaId)) {
            return null;
        }

        $leche = $this->ingresos->leche();
        $diferencia = round($nuevosLitros - $this->ingresos->neto($entregaId), 4);

        if ($diferencia === 0.0) {
            return null;
        }

        $aumenta = $diferencia > 0;

        return $this->registrarMovimiento->execute(
            insumoId: $leche->id() ?? throw new DomainException('El insumo Leche no tiene identificador válido.'),
            motivo: $aumenta ? MotivoMovimientoInsumo::INGRESO_ACOPIO : MotivoMovimientoInsumo::REVERSO,
            direccion: $aumenta ? MotivoMovimientoInsumo::ENTRADA : MotivoMovimientoInsumo::SALIDA,
            cantidad: abs($diferencia),
            registradoPorUserId: $registradoPorUserId,
            observacion: sprintf('Corrección de litros de la entrega #%d.', $entregaId),
            origenTipo: InsumoMovimiento::ORIGEN_ENTREGA,
            origenId: $entregaId,
        );
    }
}
