<?php

namespace App\Application\Calidad;

/**
 * @phpstan-type Fila array{id: int, fecha: string, proveedorNombre: string, proveedorDni: string, entregaId: int, zonaNombre: string, resultado: ?string, estado: string, confirmadoPor: ?string}
 */
final readonly class AnalisisCalidadListadoData
{
    /**
     * @param  array{total: int, aceptadas: int, observadas: int, rechazadas: int}  $kpis
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $zonas,
        public array $filas,
    ) {}
}
