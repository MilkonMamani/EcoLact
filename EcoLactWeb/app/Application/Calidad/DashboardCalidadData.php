<?php

namespace App\Application\Calidad;

use App\Domain\Comunicacion\Comunicado;

/**
 * @phpstan-type FilaMuestra array<string, mixed> Fila de MuestreosCalidadQuery::pendientes()
 * @phpstan-type FilaAnalisis array{id: int, fecha: string, proveedorNombre: string, proveedorDni: string, entregaId: int, zonaNombre: string, resultado: ?string, estado: string}
 */
final readonly class DashboardCalidadData
{
    /**
     * @param  array{analizados: int, aceptados: int, observados: int, rechazados: int}  $kpisPeriodo
     * @param  list<FilaMuestra>  $muestrasFilas  Solo muestras seleccionadas sin analizar, nunca todas las entregas.
     * @param  list<FilaAnalisis>  $ultimosAnalisisFilas
     * @param  list<Comunicado>  $comunicadosRecientes
     */
    public function __construct(
        public int $muestrasPendientes,
        public int $analizadasHoy,
        public array $kpisPeriodo,
        public array $muestrasFilas,
        public array $ultimosAnalisisFilas,
        public array $comunicadosRecientes,
    ) {}
}
