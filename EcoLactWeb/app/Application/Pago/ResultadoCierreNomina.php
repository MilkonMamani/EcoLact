<?php

namespace App\Application\Pago;

final class ResultadoCierreNomina
{
    public function __construct(
        public int $cerradas = 0,
        public int $faltasReparadas = 0,
    ) {}
}
