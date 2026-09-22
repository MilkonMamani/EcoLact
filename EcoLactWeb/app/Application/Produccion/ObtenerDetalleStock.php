<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\ProductoMovimiento;
use App\Domain\Produccion\ProductoMovimientoRepository;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\ProductoStockRepository;

/**
 * Detalle de un elemento de la pantalla unificada de Stock (producto o
 * insumo), incluyendo sus movimientos recientes. "Ver" desde Stock no abre
 * otra opción del sidebar: abre este detalle según el tipo del elemento.
 */
final class ObtenerDetalleStock
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly ProductoStockRepository $productoStock,
        private readonly ProductoMovimientoRepository $productoMovimientos,
        private readonly InsumoRepository $insumos,
        private readonly InsumoMovimientoRepository $insumoMovimientos,
    ) {}

    public function execute(string $tipo, int $id): ?DetalleStockData
    {
        return match ($tipo) {
            'PRODUCTO' => $this->detalleProducto($id),
            'INSUMO' => $this->detalleInsumo($id),
            default => null,
        };
    }

    private function detalleProducto(int $id): ?DetalleStockData
    {
        $producto = $this->productos->buscarPorId($id);

        if ($producto === null) {
            return null;
        }

        $stock = $this->productoStock->buscarPorProductoId($id);

        $origenes = [
            'INGRESO_PRODUCCION' => 'Producción',
            'VENTA' => 'Venta',
            'AJUSTE_ENTRADA' => 'Ajuste (entrada)',
            'AJUSTE_SALIDA' => 'Ajuste (salida)',
        ];

        $movimientos = collect($this->productoMovimientos->listarPorProducto($id))
            ->sortByDesc(fn (ProductoMovimiento $movimiento): string => $movimiento->fecha()->format('Y-m-d H:i:s'))
            ->map(fn (ProductoMovimiento $movimiento): array => [
                'fecha' => $movimiento->fecha()->format('d/m/Y H:i'),
                'tipo' => $movimiento->tipo(),
                'cantidad' => $movimiento->cantidad(),
                'origen' => $movimiento->observacion() ?? $origenes[$movimiento->tipo()] ?? $movimiento->tipo(),
            ])
            ->values()
            ->all();

        return new DetalleStockData(
            tipo: 'PRODUCTO',
            id: $id,
            nombre: $producto->nombre(),
            unidadMedida: $producto->unidadMedida(),
            cantidadActual: $stock?->cantidadActual() ?? 0.0,
            stockMinimo: null,
            estado: $producto->estado(),
            movimientos: $movimientos,
        );
    }

    private function detalleInsumo(int $id): ?DetalleStockData
    {
        $insumo = $this->insumos->buscarPorId($id);

        if ($insumo === null) {
            return null;
        }

        $movimientos = collect($this->insumoMovimientos->listarPorInsumo($id))
            ->sortByDesc(fn (InsumoMovimiento $movimiento): string => $movimiento->fecha()->format('Y-m-d H:i:s'))
            ->map(fn (InsumoMovimiento $movimiento): array => [
                'fecha' => $movimiento->fecha()->format('d/m/Y H:i'),
                'tipo' => $movimiento->tipo(),
                'cantidad' => $movimiento->cantidad(),
                'origen' => $movimiento->observacion() ?? $movimiento->motivoEtiqueta(),
            ])
            ->values()
            ->all();

        return new DetalleStockData(
            tipo: 'INSUMO',
            id: $id,
            nombre: $insumo->nombre(),
            unidadMedida: $insumo->unidadBase(),
            cantidadActual: $insumo->stockActual(),
            stockMinimo: $insumo->stockMinimo(),
            estado: $insumo->estado(),
            movimientos: $movimientos,
        );
    }
}
