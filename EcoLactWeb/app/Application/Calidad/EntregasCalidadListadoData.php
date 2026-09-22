<?php

namespace App\Application\Calidad;

/**
 * Datos de la pantalla principal de Calidad (administrador): una fila por
 * ENTREGA válida, tenga o no análisis. Una entrega sin análisis se muestra como
 * PENDIENTE sin necesidad de crear un análisis vacío.
 *
 * @phpstan-type Fila array{entregaId: int, analisisId: ?int, fecha: string, proveedorNombre: string, proveedorDni: string, zonaNombre: string, resultado: ?string, estado: string, litros: float}
 */
final readonly class EntregasCalidadListadoData
{
    /**
     * @param  array{pendientes: int, analizadas: int, aceptadas: int, observadas: int, rechazadas: int}  $kpis
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $zonas,
        public array $filas,
    ) {}
}
