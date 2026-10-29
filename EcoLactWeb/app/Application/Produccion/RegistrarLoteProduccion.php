<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionInsumo;
use App\Domain\Produccion\LoteProduccionInsumoRepository;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Domain\Produccion\ProduccionDetalle;
use App\Domain\Produccion\ProduccionDetalleRepository;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DateTimeImmutable;
use DomainException;

/**
 * Crea UN lote EN_PROCESO con requerimientos ya calculados y validados:
 * código LOT-00001..., sus productos planificados (con receta y versión), la
 * fotografía de consumo y un movimiento CONSUMO_PRODUCCION por insumo.
 *
 * No abre transacción ni vuelve a validar el stock: lo invoca un caso de uso
 * (IniciarProduccion o IniciarPlanificacionProduccion) que ya tiene los
 * insumos bloqueados dentro de su transacción. Aun así, RegistrarMovimientoInsumo
 * rechaza cualquier descuento que dejara un saldo negativo.
 *
 * @phpstan-import-type Linea from RequerimientosProduccion
 * @phpstan-import-type InsumoDeLinea from RequerimientosProduccion
 */
final class RegistrarLoteProduccion
{
    public function __construct(
        private readonly LoteProduccionRepository $lotes,
        private readonly ProduccionDetalleRepository $produccionDetalles,
        private readonly LoteProduccionInsumoRepository $consumos,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
    ) {}

    /**
     * @param  list<Linea>  $lineas  productos que fabrica este lote
     * @param  list<InsumoDeLinea>  $insumos  lo que consume este lote, en unidad base
     */
    public function execute(
        array $lineas,
        array $insumos,
        int $registradoPorUserId,
        ?string $observacion,
        DateTimeImmutable $fechaHoraInicio,
        ?int $planificacionProduccionId = null,
    ): LoteProduccion {
        $lote = $this->lotes->guardar(new LoteProduccion(
            id: null,
            codigo: 'PENDIENTE',
            fechaHoraInicio: $fechaHoraInicio,
            fechaHoraFin: null,
            estado: LoteProduccion::ESTADO_EN_PROCESO,
            registradoPorUserId: $registradoPorUserId,
            observacion: $observacion,
            planificacionProduccionId: $planificacionProduccionId,
        ));

        $loteId = $lote->id() ?? throw new DomainException('No se pudo registrar el lote de producción.');

        $lote = $this->lotes->actualizar(new LoteProduccion(
            id: $loteId,
            codigo: sprintf('LOT-%05d', $loteId),
            fechaHoraInicio: $lote->fechaHoraInicio(),
            fechaHoraFin: null,
            estado: LoteProduccion::ESTADO_EN_PROCESO,
            registradoPorUserId: $registradoPorUserId,
            observacion: $observacion,
            planificacionProduccionId: $planificacionProduccionId,
        ));

        foreach ($lineas as $linea) {
            $this->produccionDetalles->guardar(new ProduccionDetalle(
                id: null,
                loteProduccionId: $loteId,
                productoId: $linea['productoId'],
                cantidadPlanificada: $linea['cantidad'],
                cantidadObtenida: null,
                recetaId: $linea['recetaId'],
                recetaVersion: $linea['recetaVersion'],
            ));
        }

        foreach ($insumos as $insumo) {
            $this->consumos->guardar(new LoteProduccionInsumo(
                id: null,
                loteProduccionId: $loteId,
                insumoId: $insumo['insumoId'],
                cantidadUsada: $insumo['necesario'],
                cantidadRequerida: $insumo['necesario'],
                unidadBase: $insumo['unidadBase'],
            ));

            $this->registrarMovimiento->execute(
                insumoId: $insumo['insumoId'],
                motivo: MotivoMovimientoInsumo::CONSUMO_PRODUCCION,
                direccion: MotivoMovimientoInsumo::SALIDA,
                cantidad: $insumo['necesario'],
                registradoPorUserId: $registradoPorUserId,
                observacion: sprintf('Consumo del lote %s.', $lote->codigo()),
                origenTipo: InsumoMovimiento::ORIGEN_LOTE_PRODUCCION,
                origenId: $loteId,
                loteProduccionId: $loteId,
                fecha: $lote->fechaHoraInicio(),
            );
        }

        return $lote;
    }
}
