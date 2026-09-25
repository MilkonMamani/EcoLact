<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonal;

final readonly class ResultadoAsistenciaQr
{
    public function __construct(
        public JornadaPersonal $jornada,
        public bool $duplicado,
    ) {}
}
