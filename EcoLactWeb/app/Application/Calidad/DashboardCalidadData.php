<?php

namespace App\Application\Calidad;

use App\Domain\Comunicacion\Comunicado;

/**
 * @phpstan-type FilaPendiente array{id: int, fecha: string, proveedorNombre: string, proveedorDni: string, zonaNombre: string, litros: float}
 * @phpstan-type FilaAnalisis array{id: int, fecha: string, proveedorNombre: string, proveedorDni: string, entregaId: int, zonaNombre: string, resultado: ?string, estado: string}
 */
final readonly class DashboardCalidadData
{
    /**
     * @param  array{analizados: int, aceptados: int, observados: int, rechazados: int}  $kpisPeriodo
     * @param  list<FilaPendiente>  $pendientesFilas
     * @param  list<FilaAnalisis>  $ultimosAnalisisFilas
     * @param  list<Comunicado>  $comunicadosRecientes
     */
    public function __construct(
        public int $pendientesAnalisis,
        public array $kpisPeriodo,
        public array $pendientesFilas,
        public array $ultimosAnalisisFilas,
        public array $comunicadosRecientes,
    ) {}
}
