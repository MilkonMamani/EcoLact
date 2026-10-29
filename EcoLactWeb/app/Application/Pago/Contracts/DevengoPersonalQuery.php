<?php

namespace App\Application\Pago\Contracts;

use DateTimeImmutable;

/**
 * Hechos de solo lectura con los que CalcularDevengoPersonal valoriza el
 * trabajo realizado: jornadas, rol actual de cada usuario y obligaciones
 * mensuales de personal. Cada método es UNA consulta, sin importar cuántos
 * trabajadores o días haya.
 */
interface DevengoPersonalQuery
{
    /**
     * Roles laborales (con sus banderas) indexados por id.
     *
     * @return array<int, array{nombre: string, remunerable: bool, controlaAsistencia: bool}>
     */
    public function roles(): array;

    /**
     * Rol actual de cada usuario.
     *
     * @param  list<int>  $userIds
     * @return array<int, int> userId => rolId
     */
    public function rolActualPorUsuario(array $userIds): array;

    /**
     * Jornadas (PRESENTE/FALTA) registradas entre ambas fechas, inclusive.
     *
     * @return array<int, array<string, array{estado: string, rolId: int}>> trabajadorId => [Y-m-d => jornada]
     */
    public function jornadasEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): array;

    /**
     * Obligaciones mensuales de personal cuyo mes empieza entre ambas fechas
     * (NO_PAGABLE no aplica a personal).
     *
     * @return array<int, array<string, array{id: int, estado: string, acumulado: float, montoCalculado: float, sueldoBase: float|null, fechaPago: string|null}>> userId => [Y-m => obligación]
     */
    public function obligacionesMensuales(DateTimeImmutable $desde, DateTimeImmutable $hasta): array;
}
