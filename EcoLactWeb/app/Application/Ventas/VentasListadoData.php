<?php

namespace App\Application\Ventas;

/**
 * @phpstan-type Fila array{id: int, fecha: string, productoNombre: string, clienteNombre: string, clienteTipo: string, cantidad: float, importeTotal: float, estado: string}
 */
final readonly class VentasListadoData
{
    /**
     * @param  array{ventas: int, totalVendido: float, productosVendidos: float, compradores: int}  $kpis
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $filas,
    ) {}
}
