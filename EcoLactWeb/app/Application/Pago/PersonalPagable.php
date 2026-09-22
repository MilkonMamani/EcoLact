<?php

namespace App\Application\Pago;

use DateTimeImmutable;

/** Persona activa del personal a la que le corresponde un sueldo por su rol. */
final class PersonalPagable
{
    public function __construct(
        public readonly int $userId,
        public readonly int $rolId,
        public readonly string $nombre,
        public readonly DateTimeImmutable $fechaAlta,
    ) {}
}
