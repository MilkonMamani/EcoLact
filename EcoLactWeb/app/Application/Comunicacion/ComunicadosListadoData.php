<?php

namespace App\Application\Comunicacion;

/**
 * @phpstan-type Fila array{id: int, titulo: string, tipo: string, destinatarios: int, fecha: string, estado: string}
 */
final readonly class ComunicadosListadoData
{
    /**
     * @param  array{total: int, publicados: int, borradores: int, destinatariosAlcanzados: int}  $kpis
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $filas,
    ) {}
}
