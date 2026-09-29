<?php

namespace App\Application\Pago;

use App\Application\Shared\Reloj;
use DateTimeImmutable;
use DomainException;

/**
 * Política oficial de asistencia de EcoLact (la ÚNICA regla de cierre):
 * la asistencia de un día se registra o corrige solo ese mismo día ANTES de
 * las 07:00 (hora del negocio, config app.zona_horaria_negocio). Desde las
 * 07:00:00 el día queda CERRADO: solo consulta, y a quien debía asistir y no
 * registró nada se le marca FALTA automática (MarcarFaltasAutomaticas).
 * "Pendiente" solo existe para hoy antes del cierre.
 */
final class VentanaAsistencia
{
    public const HORA_CIERRE = '07:00';

    public function __construct(private readonly Reloj $reloj) {}

    /**
     * Fecha de hoy en la zona horaria del negocio, como día calendario a las
     * 00:00 en la zona de la app (igual que las fechas que vienen de la BD),
     * para que las comparaciones de días nunca se corran por la diferencia horaria.
     */
    public function hoy(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->reloj->hoy()->format('Y-m-d'));
    }

    /** Momento del cierre de hoy (07:00:00 hora del negocio). */
    public function cierreDeHoy(): DateTimeImmutable
    {
        [$hora, $minuto] = array_map('intval', explode(':', self::HORA_CIERRE));

        return $this->reloj->hoy()->setTime($hora, $minuto);
    }

    /** La asistencia de $fecha todavía se puede registrar o corregir. */
    public function estaAbierta(DateTimeImmutable $fecha): bool
    {
        return $fecha->format('Y-m-d') === $this->hoy()->format('Y-m-d')
            && $this->reloj->ahora() < $this->cierreDeHoy();
    }

    /** Último día ya cerrado: hoy desde las 07:00; antes de esa hora, ayer. */
    public function ultimoDiaCerrado(): DateTimeImmutable
    {
        return $this->reloj->ahora() >= $this->cierreDeHoy() ? $this->hoy() : $this->hoy()->modify('-1 day');
    }

    /**
     * Primer día que todavía puede evaluarse (hoy si su asistencia sigue
     * abierta; si no, mañana). Un trabajador que ingresa o se reactiva empieza
     * a contar desde aquí: nunca recibe falta por un día que ya cerró.
     */
    public function primerDiaEvaluable(): DateTimeImmutable
    {
        return $this->estaAbierta($this->hoy()) ? $this->hoy() : $this->hoy()->modify('+1 day');
    }

    /** Rechaza (en el backend, no solo en la vista) cualquier registro/corrección fuera de la ventana. */
    public function exigirAbierta(DateTimeImmutable $fecha): void
    {
        if ($this->estaAbierta($fecha)) {
            return;
        }

        throw new DomainException($fecha->format('Y-m-d') === $this->hoy()->format('Y-m-d')
            ? 'La asistencia de hoy ya se cerró a las '.self::HORA_CIERRE.' a. m.: solo se puede consultar.'
            : 'La asistencia de ese día ya está cerrada: solo se puede consultar.');
    }
}
