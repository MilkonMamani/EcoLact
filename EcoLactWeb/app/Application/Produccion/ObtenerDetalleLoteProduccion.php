<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\LoteProduccion;
use App\Domain\Produccion\LoteProduccionInsumoRepository;
use App\Domain\Produccion\LoteProduccionRepository;
use App\Domain\Produccion\PlanificacionProduccionRepository;
use App\Domain\Produccion\ProduccionDetalleRepository;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\ProductoStockRepository;

final class ObtenerDetalleLoteProduccion
{
    public function __construct(
        private readonly LoteProduccionRepository $lotes,
        private readonly LoteProduccionInsumoRepository $loteInsumos,
        private readonly ProduccionDetalleRepository $produccionDetalles,
        private readonly InsumoRepository $insumos,
        private readonly ProductoRepository $productos,
        private readonly ProductoStockRepository $stock,
        private readonly PlanificacionProduccionRepository $planificaciones,
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
            $obtenida = $detalle->cantidadObtenida();

            $productos[] = [
                'detalleId' => $detalle->id(),
                'productoId' => $detalle->productoId(),
                'productoNombre' => $producto?->nombre() ?? 'Producto eliminado',
                'etiqueta' => $producto?->etiqueta() ?? 'Producto eliminado',
                'unidadMedida' => $producto?->unidadMedida() ?? '—',
                'cantidadPlanificada' => $detalle->cantidadPlanificada(),
                'cantidadObtenida' => $obtenida,
                // Lotes anteriores a la columna merma: se deriva de lo planificado y lo obtenido.
                'merma' => $detalle->merma() ?? ($obtenida !== null ? round(max(0.0, $detalle->cantidadPlanificada() - $obtenida), 2) : null),
                'excedente' => $obtenida !== null ? round(max(0.0, $obtenida - $detalle->cantidadPlanificada()), 2) : 0.0,
                'stockActual' => $this->stock->buscarPorProductoId($detalle->productoId())?->cantidadActual() ?? 0.0,
                'recetaId' => $detalle->recetaId(),
                'recetaVersion' => $detalle->recetaVersion(),
            ];
        }

        return new LoteProduccionDetalleData(
            lote: $lote,
            productos: $productos,
            insumosUtilizados: $insumosUtilizados,
            lecheUtilizada: round($lecheUtilizada, 4),
            planificacion: $this->planificacionDe($lote),
        );
    }

    /**
     * Los lotes iniciados junto a este (incluido él), para navegar entre ellos.
     *
     * @return array{codigo: string, lotes: list<array{id: int, codigo: string, estado: string, producto: string, actual: bool}>}|null
     */
    private function planificacionDe(LoteProduccion $lote): ?array
    {
        $planificacionId = $lote->planificacionProduccionId();
        $planificacion = $planificacionId !== null ? $this->planificaciones->buscarPorId($planificacionId) : null;

        if ($planificacion === null) {
            return null;
        }

        $lotes = [];

        foreach ($this->lotes->listarPorPlanificacion($planificacionId) as $hermano) {
            $detalle = $this->produccionDetalles->listarPorLote((int) $hermano->id())[0] ?? null;
            $producto = $detalle !== null ? $this->productos->buscarPorId($detalle->productoId()) : null;

            $lotes[] = [
                'id' => (int) $hermano->id(),
                'codigo' => $hermano->codigo(),
                'estado' => $hermano->estado(),
                'producto' => $producto?->etiqueta() ?? 'Producto eliminado',
                'actual' => $hermano->id() === $lote->id(),
            ];
        }

        return ['codigo' => $planificacion->codigo(), 'lotes' => $lotes];
    }
}
