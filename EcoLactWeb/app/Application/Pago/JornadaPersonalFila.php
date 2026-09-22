<?php

namespace App\Application\Pago;

/** Fila del modal "Ver jornadas". */
final readonly class JornadaPersonalFila
{
    public function __construct(
        public string $fechaIso,
        public string $fechaEtiqueta,
        public ?string $observacion,
    ) {}
}
