<?php

namespace App\Application\Produccion;

use App\Domain\Entrega\Entrega;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DomainException;

/**
 * Cuando una entrega queda registrada, la leche entra automáticamente al
 * inventario de insumos: movimiento INGRESO_ACOPIO (+litros) sobre el insumo
 * LECHE, con origen ENTREGA. No depende de ningún análisis de calidad.
 *
 * Es idempotente: si la entrega ya generó su ingreso, devuelve ese movimiento
 * en vez de duplicarlo. Debe llamarse dentro de la misma transacción que
 * guarda la entrega.
 */
final class RegistrarIngresoLecheDesdeEntrega
{
    public function __construct(
        private readonly IngresosLechePorEntrega $ingresos,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
    ) {}

    public function execute(Entrega $entrega): InsumoMovimiento
    {
        $entregaId = $entrega->id() ?? throw new DomainException('La entrega debe estar guardada antes de ingresar su leche al inventario.');

        foreach ($this->ingresos->movimientosDe($entregaId) as $existente) {
            if ($existente->motivo() === MotivoMovimientoInsumo::INGRESO_ACOPIO->value) {
                return $existente;
            }
        }

        $leche = $this->ingresos->leche();

        return $this->registrarMovimiento->execute(
            insumoId: $leche->id() ?? throw new DomainException('El insumo Leche no tiene identificador válido.'),
            motivo: MotivoMovimientoInsumo::INGRESO_ACOPIO,
            direccion: MotivoMovimientoInsumo::ENTRADA,
            cantidad: $entrega->litros(),
            registradoPorUserId: $entrega->registradoPorUserId(),
            observacion: sprintf('Entrega #%d.', $entregaId),
            origenTipo: InsumoMovimiento::ORIGEN_ENTREGA,
            origenId: $entregaId,
            fecha: $entrega->fechaHora(),
            unidad: $leche->unidadBase() === 'L' ? null : 'L',
        );
    }
}
