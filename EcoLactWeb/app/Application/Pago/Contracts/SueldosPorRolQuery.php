<?php

namespace App\Application\Pago\Contracts;

use DateTimeImmutable;

/**
 * Lectura de Configuración de pagos > Trabajadores: un sueldo base por ROL
 * laboral (los roles remunerables de la tabla `roles`; PROVEEDOR no, porque
 * cobra por litros). Cada fila trae cuánto personal activo hereda ese sueldo.
 * Solo lee; guardar sigue en los Use Cases de Pago. Sin asistencia ni pagos:
 * eso es Pagos > Pagar a trabajadores.
 *
 * estado: ACTIVO (sueldo vigente hoy), PROGRAMADO (solo una vigencia futura)
 * o SIN_CONFIGURAR.
 */
interface SueldosPorRolQuery
{
    /**
     * @return array{
     *     roles: list<array{rolId: int, clave: string, rol: string, personal: int, estado: string, configuracionId: int|null, sueldo: float|null, valorDia: float|null, vigenteDesde: string|null}>,
     *     resumen: array{roles: int, configurados: int, pendientes: int}
     * }
     */
    public function listar(DateTimeImmutable $hoy): array;

    /**
     * La fila de listar() del rol más: la observación de la vigencia mostrada,
     * la próxima vigencia programada, el historial completo de versiones
     * (VIGENTE, PROGRAMADO o ANTERIOR, con quién la registró), el personal activo
     * que la hereda y, como referencia, los sueldos individuales que ese personal
     * tenía en el modelo anterior. Null si el rol no es remunerable.
     *
     * @return array<string, mixed>|null
     */
    public function detalle(int $rolId, DateTimeImmutable $hoy): ?array;
}
