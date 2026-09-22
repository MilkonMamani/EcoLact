<?php

namespace App\Application\Acopiador;

/**
 * @phpstan-type Fila array{id: int, nombre: string, dni: string, zonaNombre: string, telefono: ?string, entregas: int, estado: string}
 */
final readonly class AcopiadoresListadoData
{
    /**
     * @param  array{activos: int, inactivos: int, zonasAsignadas: int, conEntregas: int}  $kpis
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $zonas,
        public array $filas,
    ) {}
}
