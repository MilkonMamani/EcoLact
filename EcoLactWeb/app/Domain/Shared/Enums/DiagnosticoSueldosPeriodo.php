<?php

namespace App\Domain\Shared\Enums;

/**
 * Por qué "Pagar a trabajadores" no muestra filas para un periodo, cuando no
 * es simplemente que los filtros de búsqueda/rol/estado no coincidan con
 * nada (ver DiagnosticarSueldosDelPeriodo).
 */
enum DiagnosticoSueldosPeriodo: string
{
    /** Ningún rol remunerable tiene una configuración de sueldo vigente en el periodo. */
    case SIN_CONFIGURACION = 'SIN_CONFIGURACION';

    /** Hay configuración vigente para algún rol, pero ese rol no tiene personal activo. */
    case SIN_PERSONAL_ACTIVO = 'SIN_PERSONAL_ACTIVO';

    /** Hay configuración y personal activo, pero aun así no hay obligaciones (caso residual). */
    case SIN_REGISTROS = 'SIN_REGISTROS';

    public function mensaje(): string
    {
        return match ($this) {
            self::SIN_CONFIGURACION => 'No hay sueldos configurados para este periodo.',
            self::SIN_PERSONAL_ACTIVO => 'No hay trabajadores activos para las configuraciones disponibles.',
            self::SIN_REGISTROS => 'Aún no hay pagos generados para este periodo.',
        };
    }
}
