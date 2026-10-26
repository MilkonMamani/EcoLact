<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Domain\Produccion\PresentacionInsumo;
use App\Domain\Produccion\PresentacionInsumoRepository;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DomainException;

/**
 * Panel derecho de un insumo en la pantalla Insumos: datos y stock, sus
 * presentaciones de compra y los últimos movimientos con su origen legible
 * ("Compra · Boleta B001-203", "Producción LOT-00015", "Entrega #12") y su
 * clase (compra, ajuste, consumo, acopio u otro) para distinguirlos a simple vista.
 *
 * @phpstan-type MovimientoPanel array{fecha: \DateTimeImmutable, entrada: bool, cantidad: float, clase: string, motivo: string, referencia: ?string, compra_id: ?int}
 */
final class ObtenerPanelInsumo
{
    private const MOVIMIENTOS = 8;

    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly PresentacionInsumoRepository $presentaciones,
        private readonly InsumoMovimientoRepository $movimientos,
        private readonly CompraInsumoRepository $compras,
        private readonly LoteProduccionRepository $lotes,
    ) {}

    /**
     * @return array{insumo: Insumo, presentaciones: list<PresentacionInsumo>, movimientos: list<MovimientoPanel>}
     */
    public function execute(int $insumoId): array
    {
        $insumo = $this->insumos->buscarPorId($insumoId)
            ?? throw new DomainException('El insumo indicado no existe.');

        $movimientos = array_slice($this->movimientos->listarPorInsumo($insumoId), 0, self::MOVIMIENTOS);

        $compraIds = [];
        $loteIds = [];
        foreach ($movimientos as $movimiento) {
            if ($movimiento->origenTipo() === InsumoMovimiento::ORIGEN_COMPRA_INSUMO && $movimiento->origenId() !== null) {
                $compraIds[] = $movimiento->origenId();
            }

            if ($movimiento->loteProduccionId() !== null) {
                $loteIds[] = $movimiento->loteProduccionId();
            }
        }

        $compras = $this->compras->buscarVarias(array_values(array_unique($compraIds)));

        // Como mucho MOVIMIENTOS lotes distintos.
        $codigosLote = [];
        foreach (array_unique($loteIds) as $loteId) {
            $codigosLote[$loteId] = $this->lotes->buscarPorId($loteId)?->codigo();
        }

        return [
            'insumo' => $insumo,
            'presentaciones' => $insumo->esLeche() ? [] : $this->presentaciones->listarPorInsumo($insumoId),
            'movimientos' => array_map(function (InsumoMovimiento $movimiento) use ($compras, $codigosLote): array {
                $compra = $movimiento->origenTipo() === InsumoMovimiento::ORIGEN_COMPRA_INSUMO
                    ? ($compras[$movimiento->origenId()] ?? null)
                    : null;

                return [
                    'fecha' => $movimiento->fecha(),
                    'entrada' => $movimiento->esEntrada(),
                    'cantidad' => $movimiento->cantidad(),
                    'clase' => match (MotivoMovimientoInsumo::tryFrom($movimiento->motivo())) {
                        MotivoMovimientoInsumo::INGRESO_COMPRA => 'compra',
                        MotivoMovimientoInsumo::AJUSTE_ENTRADA, MotivoMovimientoInsumo::AJUSTE_SALIDA => 'ajuste',
                        MotivoMovimientoInsumo::CONSUMO_PRODUCCION => 'consumo',
                        MotivoMovimientoInsumo::INGRESO_ACOPIO => 'acopio',
                        default => 'otro',
                    },
                    'motivo' => MotivoMovimientoInsumo::tryFrom($movimiento->motivo())?->etiqueta() ?? $movimiento->motivo(),
                    'referencia' => match (true) {
                        $compra !== null => $compra->referencia(),
                        $movimiento->loteProduccionId() !== null => 'Producción '.($codigosLote[$movimiento->loteProduccionId()] ?? '#'.$movimiento->loteProduccionId()),
                        $movimiento->origenTipo() === InsumoMovimiento::ORIGEN_ENTREGA => 'Entrega #'.$movimiento->origenId(),
                        default => $movimiento->observacion(),
                    },
                    'compra_id' => $compra?->id(),
                ];
            }, $movimientos),
        ];
    }
}
