<?php

namespace App\Application\Produccion;

/**
 * Resultado de calcular qué necesita una producción: las líneas de productos
 * (con la receta que se usaría), los insumos totales requeridos (ya sumados
 * entre todos los productos) y los productos que no tienen receta activa.
 *
 * @phpstan-type Linea array{productoId: int, producto: string, etiqueta: string, unidad: string, cantidad: float, recetaId: int|null, recetaNombre: string|null, recetaVersion: int|null}
 */
final readonly class RequerimientosProduccion
{
    /**
     * @param  list<Linea>  $lineas
     * @param  list<RequerimientoInsumo>  $insumos
     * @param  list<array{productoId: int, producto: string}>  $productosSinReceta
     */
    public function __construct(
        public array $lineas,
        public array $insumos,
        public array $productosSinReceta,
    ) {}

    /**
     * @return list<RequerimientoInsumo>
     */
    public function faltantes(): array
    {
        return array_values(array_filter(
            $this->insumos,
            static fn (RequerimientoInsumo $requerimiento): bool => ! $requerimiento->suficiente(),
        ));
    }

    public function puedeIniciar(): bool
    {
        return $this->lineas !== [] && $this->productosSinReceta === [] && $this->faltantes() === [];
    }

    /**
     * @return list<string>
     */
    public function mensajesDeBloqueo(): array
    {
        $mensajes = array_map(
            static fn (array $producto): string => "{$producto['producto']}: este producto todavía no tiene una receta configurada.",
            $this->productosSinReceta,
        );

        foreach ($this->faltantes() as $faltante) {
            $mensajes[] = $faltante->mensajeFaltante();
        }

        return $mensajes;
    }

    /**
     * Frase única que resume si se puede iniciar, para mostrarla sobre el botón.
     */
    public function resumen(): string
    {
        if ($this->productosSinReceta !== []) {
            return 'Este producto todavía no tiene una receta configurada.';
        }

        return $this->faltantes() === []
            ? 'Todos los insumos están disponibles.'
            : 'No hay suficientes insumos para iniciar esta producción.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lineas' => $this->lineas,
            'insumos' => array_map(static fn (RequerimientoInsumo $r): array => $r->toArray(), $this->insumos),
            'productos_sin_receta' => $this->productosSinReceta,
            'puede_iniciar' => $this->puedeIniciar(),
            'mensajes' => $this->mensajesDeBloqueo(),
            'resumen' => $this->resumen(),
        ];
    }
}
