<?php

namespace App\Application\Compra;

use App\Application\Produccion\CalcularCompraInsumos;
use App\Domain\Shared\Enums\TratamientoCompra;
use DomainException;

/**
 * Calcula, SIN registrar ni mover nada, una compra o gasto con líneas mixtas:
 *
 *   INVENTARIO   delega en CalcularCompraInsumos (la única conversión
 *                presentación -> unidad base y el stock resultante),
 *   CONSUMO, BIEN, SERVICIO  descripción + cantidad × precio unitario.
 *
 * Subtotal = suma de los subtotales; total = subtotal + impuesto. Si alguna
 * línea no tiene precio, subtotal y total son null (no se inventan). La
 * vista previa de la pantalla y RegistrarCompra usan este mismo cálculo.
 *
 * @phpstan-import-type LineaCalculada from CalcularCompraInsumos
 * @phpstan-import-type Impacto from CalcularCompraInsumos
 *
 * @phpstan-type LineaEntrada array{tratamiento?: ?string, insumo_id?: int|string|null, presentacion_id?: int|string|null, descripcion?: ?string, cantidad?: float|int|string|null, unidad?: ?string, precio_unitario?: float|int|string|null}
 * @phpstan-type LineaCompra array{tratamiento: string, tratamiento_etiqueta: string, descripcion: string, cantidad: float, unidad: ?string, precio_unitario: ?float, subtotal: ?float, inventario: ?array<string, mixed>}
 */
final class CalcularCompra
{
    public function __construct(
        private readonly CalcularCompraInsumos $calcularInsumos,
    ) {}

    /**
     * @param  list<LineaEntrada>  $lineas
     * @return array{lineas: list<LineaCompra>, impacto: list<Impacto>, subtotal: ?float, impuesto: float, total: ?float, genera_stock: bool}
     *
     * @throws DomainException con un mensaje para una persona no técnica.
     */
    public function execute(array $lineas, float|int|string|null $impuesto = 0): array
    {
        if ($lineas === []) {
            throw new DomainException('Agregue al menos un artículo o concepto a la compra.');
        }

        $impuesto = round((float) ($impuesto ?? 0), 2);
        if ($impuesto < 0.0) {
            throw new DomainException('El impuesto no puede ser negativo.');
        }

        $deInventario = [];
        foreach ($lineas as $indice => $linea) {
            if ($this->tratamiento($linea) === TratamientoCompra::INVENTARIO) {
                $deInventario[$indice] = $linea;
            }
        }

        $calculoInsumos = $deInventario !== [] ? $this->calcularInsumos->execute(array_values($deInventario)) : ['lineas' => [], 'impacto' => []];
        $insumosPorIndice = array_combine(array_keys($deInventario), $calculoInsumos['lineas']);

        $calculadas = [];
        foreach ($lineas as $indice => $linea) {
            $tratamiento = $this->tratamiento($linea);

            if ($tratamiento === TratamientoCompra::INVENTARIO) {
                $insumo = $insumosPorIndice[$indice];
                $calculadas[] = [
                    'tratamiento' => $tratamiento->value,
                    'tratamiento_etiqueta' => $tratamiento->etiqueta(),
                    'descripcion' => $insumo['insumo'],
                    'cantidad' => $insumo['cantidad'],
                    'unidad' => $insumo['presentacion'],
                    'precio_unitario' => $insumo['precio_unitario'],
                    'subtotal' => $insumo['subtotal'],
                    'inventario' => $insumo,
                ];

                continue;
            }

            $descripcion = trim((string) ($linea['descripcion'] ?? ''));
            $cantidad = round((float) ($linea['cantidad'] ?? 0), 2);
            $precio = isset($linea['precio_unitario']) && $linea['precio_unitario'] !== '' ? round((float) $linea['precio_unitario'], 2) : null;
            $unidad = trim((string) ($linea['unidad'] ?? '')) ?: null;

            if ($descripcion === '') {
                throw new DomainException('Describe qué se compró en cada línea.');
            }
            if ($cantidad <= 0.0) {
                throw new DomainException("La cantidad de «{$descripcion}» debe ser mayor que cero.");
            }
            if ($precio !== null && $precio < 0.0) {
                throw new DomainException("El precio de «{$descripcion}» no puede ser negativo.");
            }

            $calculadas[] = [
                'tratamiento' => $tratamiento->value,
                'tratamiento_etiqueta' => $tratamiento->etiqueta(),
                'descripcion' => mb_substr($descripcion, 0, 150),
                'cantidad' => $cantidad,
                'unidad' => $unidad !== null ? mb_substr($unidad, 0, 20) : null,
                'precio_unitario' => $precio,
                'subtotal' => $precio !== null ? round($precio * $cantidad, 2) : null,
                'inventario' => null,
            ];
        }

        $subtotales = array_column($calculadas, 'subtotal');
        $subtotal = in_array(null, $subtotales, true) ? null : round(array_sum($subtotales), 2);

        return [
            'lineas' => $calculadas,
            'impacto' => $calculoInsumos['impacto'],
            'subtotal' => $subtotal,
            'impuesto' => $impuesto,
            'total' => $subtotal !== null ? round($subtotal + $impuesto, 2) : null,
            'genera_stock' => $deInventario !== [],
        ];
    }

    /**
     * Sin tratamiento explícito, una línea con insumo es de inventario (así
     * llegan las compras de la app móvil); cualquier otra, consumo.
     *
     * @param  LineaEntrada  $linea
     */
    private function tratamiento(array $linea): TratamientoCompra
    {
        $tratamiento = TratamientoCompra::tryFrom((string) ($linea['tratamiento'] ?? ''));

        if ($tratamiento !== null) {
            return $tratamiento;
        }

        return ! empty($linea['insumo_id']) ? TratamientoCompra::INVENTARIO : TratamientoCompra::CONSUMO;
    }
}
