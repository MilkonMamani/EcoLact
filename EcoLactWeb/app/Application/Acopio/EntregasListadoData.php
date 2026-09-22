<?php

namespace App\Application\Acopio;

/**
 * @phpstan-type Fila array{id: int, fecha: string, proveedorNombre: string, proveedorDni: string, zonaNombre: string, acopiadorNombre: string, litros: float, metodoIdentificacion: string, estado: string, resultado: ?string}
 */
final readonly class EntregasListadoData
{
    /**
     * @param  array{litrosRegistrados: float, entregas: int, pendientesAnalisis: int, analizadas: int}  $kpis
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<array{id: int, nombre: string}>  $acopiadores
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $zonas,
        public array $acopiadores,
        public array $filas,
    ) {}
}
