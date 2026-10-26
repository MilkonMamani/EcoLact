<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\RecetaRepository;
use App\Domain\Produccion\UnidadMedida;
use DomainException;

/**
 * Calcula, SIN mover inventario, qué insumos necesita una producción:
 *
 *   por cada producto: ingredientes de su receta activa x (cantidad a
 *   producir / rendimiento de la receta), convertidos a la unidad base de
 *   cada insumo y SUMADOS entre todos los productos (si tres productos usan
 *   leche, se muestra un solo total de leche).
 *
 * Es la única fuente de estos números: la pantalla de previsualización y
 * IniciarProduccion usan este mismo cálculo, así lo que ve la persona es lo
 * que se descuenta. Con `$bloquearInsumos` (solo dentro de la transacción de
 * IniciarProduccion) lee el saldo con FOR UPDATE, en orden de id para evitar
 * bloqueos cruzados entre dos producciones simultáneas.
 */
final class CalcularRequerimientosProduccion
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly RecetaRepository $recetas,
        private readonly InsumoRepository $insumos,
    ) {}

    /**
     * @param  list<array{producto_id: int|string, cantidad: float|int|string}>  $lineas
     */
    public function execute(array $lineas, bool $bloquearInsumos = false): RequerimientosProduccion
    {
        $cantidades = $this->consolidar($lineas);

        if ($cantidades === []) {
            throw new DomainException('Agregue al menos un producto a elaborar.');
        }

        $lineasCalculadas = [];
        $sinReceta = [];
        $necesarioPorInsumo = [];

        foreach ($cantidades as $productoId => $cantidad) {
            $producto = $this->productos->buscarPorId($productoId)
                ?? throw new DomainException('Uno de los productos seleccionados no existe.');

            if ($producto->estado() !== 'ACTIVO') {
                throw new DomainException("El producto {$producto->nombre()} está inactivo y no se puede producir.");
            }

            $receta = $this->recetas->buscarActivaPorProducto($productoId);

            $lineasCalculadas[] = [
                'productoId' => $productoId,
                'producto' => $producto->nombre(),
                'etiqueta' => $producto->etiqueta(),
                'unidad' => $producto->unidadMedida(),
                'cantidad' => $cantidad,
                'recetaId' => $receta?->id(),
                'recetaNombre' => $receta?->nombre(),
                'recetaVersion' => $receta?->version(),
            ];

            if ($receta === null) {
                $sinReceta[] = ['productoId' => $productoId, 'producto' => $producto->nombre()];

                continue;
            }

            $factor = $receta->factorPara($cantidad);

            foreach ($receta->detalles() as $detalle) {
                $insumo = $this->insumos->buscarPorId($detalle->insumoId())
                    ?? throw new DomainException("Un ingrediente de la receta de {$producto->nombre()} ya no existe.");

                $necesarioPorInsumo[$detalle->insumoId()] = ($necesarioPorInsumo[$detalle->insumoId()] ?? 0.0)
                    + UnidadMedida::convertir($detalle->cantidad() * $factor, $detalle->unidad(), $insumo->unidadBase());
            }
        }

        // La LECHE va siempre primero; el resto, en orden estable.
        $lecheId = $this->insumos->buscarPorCodigo(Insumo::CODIGO_LECHE)?->id();
        uksort($necesarioPorInsumo, static fn (int $a, int $b): int => [$a !== $lecheId, $a] <=> [$b !== $lecheId, $b]);

        $requerimientos = [];

        foreach ($necesarioPorInsumo as $insumoId => $necesario) {
            $insumo = ($bloquearInsumos ? $this->insumos->bloquearPorId($insumoId) : $this->insumos->buscarPorId($insumoId))
                ?? throw new DomainException('Uno de los insumos de la receta ya no existe.');

            $requerimientos[] = new RequerimientoInsumo(
                insumoId: $insumoId,
                nombre: $insumo->nombre(),
                unidadBase: $insumo->unidadBase(),
                necesario: round($necesario, 4),
                disponible: $insumo->stockActual(),
            );
        }

        return new RequerimientosProduccion($lineasCalculadas, $requerimientos, $sinReceta);
    }

    /**
     * Suma las líneas repetidas del mismo producto y descarta las vacías.
     *
     * @param  list<array{producto_id: int|string, cantidad: float|int|string}>  $lineas
     * @return array<int, float>
     */
    private function consolidar(array $lineas): array
    {
        $cantidades = [];

        foreach ($lineas as $linea) {
            $productoId = (int) ($linea['producto_id'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);

            if ($productoId === 0) {
                continue;
            }

            if ($cantidad <= 0.0) {
                throw new DomainException('La cantidad de cada producto a elaborar debe ser mayor que cero.');
            }

            $cantidades[$productoId] = round(($cantidades[$productoId] ?? 0.0) + $cantidad, 2);
        }

        return $cantidades;
    }
}
