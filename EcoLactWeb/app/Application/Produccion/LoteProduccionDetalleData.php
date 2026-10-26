<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\LoteProduccion;

/**
 * Detalle de un lote: productos planificados y obtenidos, insumos consumidos
 * (fotografía histórica) y la leche utilizada, derivada del consumo real del
 * insumo LECHE registrado en el lote (no es un dato manual).
 *
 * `stockActual` es el saldo REAL de hoy de cada producto terminado: con él la
 * pantalla muestra, antes de finalizar, cuánto quedará en inventario. Es solo
 * una vista previa; el stock sube únicamente al finalizar.
 *
 * @phpstan-type Producto array{detalleId: int, productoId: int, productoNombre: string, etiqueta: string, unidadMedida: string, cantidadPlanificada: float, cantidadObtenida: float|null, merma: float|null, excedente: float, stockActual: float, recetaId: int|null, recetaVersion: int|null}
 * @phpstan-type InsumoUsado array{insumoId: int, insumoNombre: string, unidadBase: string, cantidadRequerida: float, cantidadUsada: float, esLeche: bool}
 */
final readonly class LoteProduccionDetalleData
{
    /**
     * @param  list<Producto>  $productos
     * @param  list<InsumoUsado>  $insumosUtilizados
     */
    public function __construct(
        public LoteProduccion $lote,
        public array $productos,
        public array $insumosUtilizados,
        public float $lecheUtilizada,
    ) {}

    public function estaEnProceso(): bool
    {
        return $this->lote->estaEnProceso();
    }

    /**
     * Nombre corto de lo fabricado: "Queso Paria · 1 kg" o "Queso Paria · 1 kg y 1 más".
     */
    public function titulo(): string
    {
        if ($this->productos === []) {
            return 'Sin productos';
        }

        $restantes = count($this->productos) - 1;

        return $this->productos[0]['etiqueta'].($restantes > 0 ? " y {$restantes} más" : '');
    }

    /**
     * Mensaje de éxito al finalizar: "Se agregaron 9 unidades al stock de Queso Paria · 1 kg."
     */
    public function resumenStockIncorporado(): string
    {
        $partes = [];

        foreach ($this->productos as $producto) {
            if (($producto['cantidadObtenida'] ?? 0.0) > 0.0) {
                $partes[] = self::cantidad($producto['cantidadObtenida'], $producto['unidadMedida']).' al stock de '.$producto['etiqueta'];
            }
        }

        if ($partes === []) {
            return 'No se agregó stock porque no se obtuvo producto.';
        }

        $ultima = array_pop($partes);

        return 'Se agregaron '.($partes === [] ? $ultima : implode(', ', $partes).' y '.$ultima).'.';
    }

    /**
     * "9 unidades", "1 unidad", "2,5 kg": la cantidad con su unidad legible.
     */
    public static function cantidad(float $valor, string $unidad): string
    {
        $numero = RegistrarMovimientoInsumo::formato($valor);

        if ($unidad === 'unidad') {
            return $numero.' '.(abs($valor - 1.0) < 0.00001 ? 'unidad' : 'unidades');
        }

        return $numero.' '.$unidad;
    }
}
