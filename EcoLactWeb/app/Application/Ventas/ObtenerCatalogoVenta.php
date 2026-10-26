<?php

namespace App\Application\Ventas;

use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\ProductoStockRepository;

/**
 * Productos que se pueden elegir en "Nueva venta": activos y con al menos un
 * precio vigente utilizable, con su stock y sus precios. Solo sirve para
 * PREVISUALIZAR en el navegador; RegistrarVentaProducto vuelve a calcularlo
 * todo al confirmar.
 *
 * @phpstan-type ProductoVenta array{id: int, nombre: string, stock: float, publico: ?float, mayorista: ?float, minimoMayorista: ?float, proveedor: ?float}
 */
final class ObtenerCatalogoVenta
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly ProductoStockRepository $stock,
        private readonly ObtenerTarifasProducto $tarifas,
    ) {}

    /**
     * @return list<ProductoVenta>
     */
    public function execute(): array
    {
        $catalogo = [];

        foreach ($this->productos->listarActivos() as $producto) {
            $tarifas = $this->tarifas->execute($producto->id());

            if (! $tarifas->sePuedeVender()) {
                continue;
            }

            $catalogo[] = [
                'id' => $producto->id(),
                'nombre' => $producto->etiqueta(),
                'stock' => $this->stock->buscarPorProductoId($producto->id())?->cantidadActual() ?? 0.0,
                'publico' => $tarifas->publico?->precio(),
                'mayorista' => $tarifas->minimoMayorista() !== null ? $tarifas->mayorista->precio() : null,
                'minimoMayorista' => $tarifas->minimoMayorista(),
                'proveedor' => $tarifas->proveedor?->precio(),
            ];
        }

        usort($catalogo, fn (array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));

        return $catalogo;
    }
}
