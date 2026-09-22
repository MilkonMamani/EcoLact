<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\LoteProduccionInsumoRepository;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Domain\Produccion\ProduccionDetalleRepository;
use App\Domain\Produccion\ProductoRepository;

final class ObtenerDetalleLoteProduccion
{
    public function __construct(
        private readonly LoteProduccionRepository $lotes,
        private readonly LoteProduccionInsumoRepository $loteInsumos,
        private readonly ProduccionDetalleRepository $produccionDetalles,
        private readonly InsumoRepository $insumos,
        private readonly ProductoRepository $productos,
    ) {}

    public function execute(int $loteId): ?LoteProduccionDetalleData
    {
        $lote = $this->lotes->buscarPorId($loteId);

        if ($lote === null) {
            return null;
        }

        $lecheId = $this->insumos->buscarPorCodigo('LECHE')?->id();
        $lecheUtilizada = 0.0;
        $insumosUtilizados = [];

        foreach ($this->loteInsumos->listarPorLote($loteId) as $consumo) {
            $insumo = $this->insumos->buscarPorId($consumo->insumoId());
            $esLeche = $lecheId !== null && $consumo->insumoId() === $lecheId;

            if ($esLeche) {
                $lecheUtilizada += $consumo->cantidadUsada();
            }

            $insumosUtilizados[] = [
                'insumoId' => $consumo->insumoId(),
                'insumoNombre' => $insumo?->nombre() ?? 'Insumo eliminado',
                'unidadBase' => $consumo->unidadBase() ?? $insumo?->unidadBase() ?? '—',
                'cantidadRequerida' => $consumo->cantidadRequerida(),
                'cantidadUsada' => $consumo->cantidadUsada(),
                'esLeche' => $esLeche,
            ];
        }

        usort($insumosUtilizados, static fn (array $a, array $b): int => [! $a['esLeche']] <=> [! $b['esLeche']]);

        $productos = [];

        foreach ($this->produccionDetalles->listarPorLote($loteId) as $detalle) {
            $producto = $this->productos->buscarPorId($detalle->productoId());

            $productos[] = [
                'detalleId' => $detalle->id(),
                'productoId' => $detalle->productoId(),
                'productoNombre' => $producto?->nombre() ?? 'Producto eliminado',
                'unidadMedida' => $producto?->unidadMedida() ?? '—',
                'cantidadPlanificada' => $detalle->cantidadPlanificada(),
                'cantidadObtenida' => $detalle->cantidadObtenida(),
                'recetaId' => $detalle->recetaId(),
                'recetaVersion' => $detalle->recetaVersion(),
            ];
        }

        return new LoteProduccionDetalleData(
            lote: $lote,
            productos: $productos,
            insumosUtilizados: $insumosUtilizados,
            lecheUtilizada: round($lecheUtilizada, 4),
        );
    }
}
