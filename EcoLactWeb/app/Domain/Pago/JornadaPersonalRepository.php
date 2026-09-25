<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use DateTimeImmutable;

interface JornadaPersonalRepository
{
    /**
     * Guarda la jornada. Lanza DomainException si ya existe una jornada para
     * ese trabajador en esa fecha (defensa en profundidad: el índice único
     * (trabajador_id, fecha) ya lo impide a nivel de base de datos).
     */
    public function registrar(JornadaPersonal $jornada): JornadaPersonal;

    public function buscarPorId(int $id): ?JornadaPersonal;

    public function existeEnFecha(int $trabajadorId, DateTimeImmutable $fecha): bool;

    public function buscarEnFecha(int $trabajadorId, DateTimeImmutable $fecha): ?JornadaPersonal;

    public function contarEnPeriodo(int $trabajadorId, DateTimeImmutable $inicio, DateTimeImmutable $fin, ?EstadoJornada $estado = null): int;

    /**
     * @return JornadaPersonal[] ordenadas por fecha descendente
     */
    public function listarEnPeriodo(int $trabajadorId, DateTimeImmutable $inicio, DateTimeImmutable $fin): array;

    /** Corrección administrativa: cambia estado/origen de una jornada ya existente. */
    public function actualizarEstado(int $jornadaId, EstadoJornada $nuevoEstado, OrigenJornada $origen, ?string $observacion): JornadaPersonal;
}
