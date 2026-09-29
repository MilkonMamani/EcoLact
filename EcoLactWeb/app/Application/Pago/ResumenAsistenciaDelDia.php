<?php

namespace App\Application\Pago;

/**
 * Contadores de la pantalla "Asistencia de hoy" sobre todo el filtro
 * aplicado (no solo la página visible). Los calcula
 * AsistenciaDelDiaQuery::resumen() en SQL; nunca Blade.
 */
final readonly class ResumenAsistenciaDelDia
{
    public function __construct(
        public int $trabajadores,
        public int $presentes,
        public int $faltas,
        public int $sinRegistro,
    ) {}
}
