<?php

namespace App\Application\Pago\Contracts;

use DateTimeImmutable;

/**
 * Lectura de Configuración de pagos > Trabajadores: los trabajadores REALES de
 * Trabajadores (acopiadores, analistas de calidad, encargados de planta y
 * personal de ventas) y, como ya cobraban sueldo, las personas de roles
 * remunerables sin ficha (el administrador), con su sueldo vigente. No duplica
 * personas: lee las fichas, los usuarios y sus configuraciones de sueldo. Solo lee; guardar sigue en los Use
 * Cases de Pago. Sin asistencia ni pagos: eso es Pagos > Pagar a trabajadores.
 *
 * Un trabajador inactivo solo aparece si alguna vez tuvo sueldo (historial).
 *
 * estado: ACTIVO (sueldo vigente hoy), PROGRAMADO (solo una vigencia futura),
 * SIN_CONFIGURAR, o INACTIVO (el trabajador ya no está activo).
 */
interface SueldosTrabajadoresQuery
{
    /**
     * @return list<array{userId: int, nombre: string, dni: string|null, rol: string, estado: string, configuracionId: int|null, sueldo: float|null, valorDia: float|null, vigenteDesde: string|null}>
     */
    public function listar(DateTimeImmutable $hoy): array;

    /**
     * La fila de listar() del trabajador más su observación, la próxima vigencia
     * programada (si hay una además de la vigente) y las vigencias anteriores.
     * Null si no es un trabajador de la lista.
     *
     * @return array<string, mixed>|null
     */
    public function detalle(int $userId, DateTimeImmutable $hoy): ?array;
}
