<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DomainException;

/**
 * Deshace el ingreso de leche de una entrega (por ejemplo, al anularla) con
 * un movimiento REVERSO equivalente: el INGRESO_ACOPIO original no se borra,
 * así se conserva la trazabilidad.
 *
 * Si la entrega nunca ingresó leche (por ejemplo, una muestra rechazada que
 * jamás se acopió) o ya está revertida, no hace nada y devuelve null. Si la
 * leche ya se usó en producción y el stock no alcanza para revertirla, lo
 * rechaza: el stock nunca queda negativo.
 */
final class RevertirIngresoLeche
{
    public function __construct(
        private readonly IngresosLechePorEntrega $ingresos,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
    ) {}

    public function execute(int $entregaId, int $registradoPorUserId, ?string $motivo = null): ?InsumoMovimiento
    {
        $neto = $this->ingresos->neto($entregaId);

        if ($neto <= 0.0) {
            return null;
        }

        $leche = $this->ingresos->leche();

        if ($leche->stockActual() + 0.00005 < $neto) {
            throw new DomainException(sprintf(
                'No se puede anular esta entrega: %s L de su leche ya no están disponibles (se usaron en producción). Stock actual de leche: %s L.',
                RegistrarMovimientoInsumo::formato($neto),
                RegistrarMovimientoInsumo::formato($leche->stockActual()),
            ));
        }

        return $this->registrarMovimiento->execute(
            insumoId: $leche->id() ?? throw new DomainException('El insumo Leche no tiene identificador válido.'),
            motivo: MotivoMovimientoInsumo::REVERSO,
            direccion: MotivoMovimientoInsumo::SALIDA,
            cantidad: $neto,
            registradoPorUserId: $registradoPorUserId,
            observacion: trim(sprintf('Reverso del ingreso de la entrega #%d.', $entregaId).($motivo ? ' Motivo: '.$motivo : '')),
            origenTipo: InsumoMovimiento::ORIGEN_ENTREGA,
            origenId: $entregaId,
        );
    }
}
