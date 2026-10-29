<?php

namespace App\Domain\Shared\Enums;

/**
 * Situación de asistencia de un usuario en un día, para decidir si puede
 * OPERAR (registrar, modificar). Consultar nunca depende de esto.
 *
 * Solo TIENE_ASISTENCIA (jornada PRESENTE) y NO_REQUERIDA permiten operar.
 * EstadoJornada solo tiene PRESENTE y FALTA: no existen tardanza ni
 * justificación, y todos los días son laborables (no hay calendario de días
 * no laborables), así que no se inventan esos estados.
 */
enum CondicionAsistencia: string
{
    /** El rol no marca asistencia (ADMINISTRADOR) o no es personal (PROVEEDOR). */
    case NO_REQUERIDA = 'NO_REQUERIDA';

    /** Jornada PRESENTE registrada por el administrador ese día. */
    case TIENE_ASISTENCIA = 'TIENE_ASISTENCIA';

    /** Todavía no hay jornada ese día. */
    case SIN_ASISTENCIA = 'SIN_ASISTENCIA';

    /** Jornada FALTA (automática del cierre de las 23:59 o registrada así). */
    case FALTA = 'FALTA';

    /** Ese día no estaba dentro de ninguno de sus periodos de actividad. */
    case FUERA_DE_PERIODO_ACTIVO = 'FUERA_DE_PERIODO_ACTIVO';

    /** Su usuario no tiene ficha de trabajador (no tiene QR ni jornadas). */
    case NO_REGISTRADO = 'NO_REGISTRADO';

    public function puedeOperar(): bool
    {
        return $this === self::NO_REQUERIDA || $this === self::TIENE_ASISTENCIA;
    }
}
