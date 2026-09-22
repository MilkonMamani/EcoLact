<?php

namespace App\Domain\Pago;

use DateTimeImmutable;

interface JornadaPersonalRepository
{
    /**
     * Guarda la jornada. Lanza DomainException si ya existe una jornada para
     * ese usuario en esa fecha (defensa en profundidad: el índice único
     * (user_id, fecha) ya lo impide a nivel de base de datos).
     */
    public function registrar(JornadaPersonal $jornada): JornadaPersonal;

    public function existeEnFecha(int $userId, DateTimeImmutable $fecha): bool;

    public function contarEnPeriodo(int $userId, DateTimeImmutable $inicio, DateTimeImmutable $fin): int;

    /**
     * @return JornadaPersonal[] ordenadas por fecha descendente
     */
    public function listarEnPeriodo(int $userId, DateTimeImmutable $inicio, DateTimeImmutable $fin): array;
}
