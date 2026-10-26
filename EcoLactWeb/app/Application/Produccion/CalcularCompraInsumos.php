<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\PresentacionInsumoRepository;
use DomainException;

/**
 * Calcula, SIN mover inventario, cuánto entra al stock con una compra:
 *
 *   por línea: cantidad de presentaciones x equivalencia de la presentación
 *   (2 × Saco 25 kg = 50 000 g), y por insumo: stock actual + compra = stock
 *   resultante (si el mismo insumo aparece en dos líneas, se suman).
 *
 * Es la única fuente de estos números: la vista previa y RegistrarCompraInsumos
 * usan este mismo cálculo, así lo que ve la persona es lo que se ingresa. La
 * equivalencia sale de la presentación configurada (calculada con
 * UnidadMedida al guardarla), nunca de la boleta.
 *
 * El precio unitario de cada línea es opcional (S/ por presentación). Si todas
 * las líneas lo tienen, `total_precios` es la suma de cantidad × precio; si
 * falta alguno, es null y el total de la compra se indica aparte.
 *
 * @phpstan-type LineaCalculada array{insumo_id: int, insumo: string, unidad_base: string, presentacion_id: int, presentacion: string, presentacion_nombre: string, presentacion_cantidad: float, presentacion_unidad: string, cantidad: float, equivalencia: float, total: float, precio_unitario: ?float, subtotal: ?float}
 * @phpstan-type Impacto array{insumo_id: int, insumo: string, unidad_base: string, stock_actual: float, compra: float, stock_resultante: float}
 */
final class CalcularCompraInsumos
{
    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly PresentacionInsumoRepository $presentaciones,
    ) {}

    /**
     * @param  list<array{insumo_id: int|string, presentacion_id: int|string, cantidad: float|int|string, precio_unitario?: float|int|string|null}>  $lineas
     * @return array{lineas: list<LineaCalculada>, impacto: list<Impacto>, total_precios: ?float}
     *
     * @throws DomainException con un mensaje para una persona no técnica.
     */
    public function execute(array $lineas): array
    {
        if ($lineas === []) {
            throw new DomainException('Agregue al menos un insumo a la compra.');
        }

        $calculadas = [];
        /** @var array<int, Insumo> $insumosUsados */
        $insumosUsados = [];
        $compraPorInsumo = [];

        foreach ($lineas as $linea) {
            $insumoId = (int) ($linea['insumo_id'] ?? 0);
            $cantidad = round((float) ($linea['cantidad'] ?? 0), 2);
            $precio = isset($linea['precio_unitario']) && $linea['precio_unitario'] !== '' ? round((float) $linea['precio_unitario'], 2) : null;

            $insumo = $insumosUsados[$insumoId] ??= ($this->insumos->buscarPorId($insumoId)
                ?? throw new DomainException('Seleccione el insumo de cada línea.'));

            if ($insumo->esLeche()) {
                throw new DomainException('La leche no se compra: su stock aumenta solo desde Acopio.');
            }

            if ($insumo->estado() !== 'ACTIVO') {
                throw new DomainException("{$insumo->nombre()} está inactivo y no se puede comprar.");
            }

            $presentacion = $this->presentaciones->buscarPorId((int) ($linea['presentacion_id'] ?? 0));

            if ($presentacion === null || $presentacion->insumoId() !== $insumoId) {
                throw new DomainException("Seleccione la presentación de {$insumo->nombre()}.");
            }

            if (! $presentacion->estaActiva()) {
                throw new DomainException("La presentación {$presentacion->etiqueta()} de {$insumo->nombre()} está inactiva.");
            }

            if ($cantidad <= 0.0) {
                throw new DomainException("La cantidad de {$insumo->nombre()} debe ser mayor que cero.");
            }

            if ($precio !== null && $precio < 0.0) {
                throw new DomainException("El precio de {$insumo->nombre()} no puede ser negativo.");
            }

            $total = round($cantidad * $presentacion->cantidadBase(), 4);
            $compraPorInsumo[$insumoId] = ($compraPorInsumo[$insumoId] ?? 0.0) + $total;

            $calculadas[] = [
                'insumo_id' => $insumoId,
                'insumo' => $insumo->nombre(),
                'unidad_base' => $insumo->unidadBase(),
                'presentacion_id' => (int) $presentacion->id(),
                'presentacion' => $presentacion->etiqueta(),
                'presentacion_nombre' => $presentacion->nombre(),
                'presentacion_cantidad' => $presentacion->cantidad(),
                'presentacion_unidad' => $presentacion->unidad(),
                'cantidad' => $cantidad,
                'equivalencia' => $presentacion->cantidadBase(),
                'total' => $total,
                'precio_unitario' => $precio,
                'subtotal' => $precio !== null ? round($precio * $cantidad, 2) : null,
            ];
        }

        $impacto = [];

        foreach ($compraPorInsumo as $insumoId => $compra) {
            $insumo = $insumosUsados[$insumoId];

            $impacto[] = [
                'insumo_id' => $insumoId,
                'insumo' => $insumo->nombre(),
                'unidad_base' => $insumo->unidadBase(),
                'stock_actual' => $insumo->stockActual(),
                'compra' => round($compra, 4),
                'stock_resultante' => round($insumo->stockActual() + $compra, 4),
            ];
        }

        $subtotales = array_column($calculadas, 'subtotal');

        return [
            'lineas' => $calculadas,
            'impacto' => $impacto,
            'total_precios' => in_array(null, $subtotales, true) ? null : round(array_sum($subtotales), 2),
        ];
    }
}
