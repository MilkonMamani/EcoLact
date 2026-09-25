<?php

namespace App\Application\Trabajador;

/** Resumen de una corrida de SincronizarTrabajadores. */
final class ResultadoSincronizacionTrabajadores
{
    public function __construct(
        public int $creados = 0,
        public int $reactivados = 0,
        public int $desactivados = 0,
    ) {}
}
