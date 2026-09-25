<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\PeriodicidadPago;
use DateTimeImmutable;

/**
 * Periodo de pago: la semana de EcoLact (de jueves a miércoles, la misma que
 * usan las liquidaciones de proveedores) o el mes calendario (personal).
 */
final class PeriodoPago
{
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    private function __construct(
        public readonly PeriodicidadPago $periodicidad,
        public readonly DateTimeImmutable $inicio,
        public readonly DateTimeImmutable $fin,
    ) {}

    public static function que(PeriodicidadPago $periodicidad, DateTimeImmutable $fecha): self
    {
        return $periodicidad === PeriodicidadPago::SEMANAL
            ? self::semanaQueContiene($fecha)
            : self::mesQueContiene($fecha);
    }

    public static function semanaQueContiene(DateTimeImmutable $fecha): self
    {
        $dia = $fecha->setTime(0, 0);
        $diasDesdeJueves = ((int) $dia->format('N') - 4 + 7) % 7;
        $inicio = $dia->modify("-{$diasDesdeJueves} days");

        return new self(PeriodicidadPago::SEMANAL, $inicio, $inicio->modify('+6 days'));
    }

    public static function mesQueContiene(DateTimeImmutable $fecha): self
    {
        $inicio = $fecha->modify('first day of this month')->setTime(0, 0);

        return new self(PeriodicidadPago::MENSUAL, $inicio, $inicio->modify('last day of this month'));
    }

    public function siguiente(): self
    {
        return self::que($this->periodicidad, $this->fin->modify('+1 day'));
    }

    /** Identificador estable del periodo (su fecha de inicio). */
    public function clave(): string
    {
        return $this->inicio->format('Y-m-d');
    }

    public function haComenzado(DateTimeImmutable $hoy): bool
    {
        return $this->inicio <= $hoy->setTime(0, 0);
    }

    public function estaCerrado(DateTimeImmutable $hoy): bool
    {
        return $this->fin < $hoy->setTime(0, 0);
    }

    /**
     * Fecha con la que se decide qué configuración aplica: el cierre del
     * periodo o hoy, lo que ocurra primero (un mes en curso usa la
     * configuración vigente hoy).
     */
    public function fechaDeReferencia(DateTimeImmutable $hoy): DateTimeImmutable
    {
        $hoy = $hoy->setTime(0, 0);

        return $this->fin < $hoy ? $this->fin : $hoy;
    }

    /**
     * Día en que un pendiente de este periodo queda habilitado para pagarse:
     * el día siguiente a su fin (para MENSUAL, el día 1 del mes siguiente).
     */
    public function fechaPagoProgramada(): DateTimeImmutable
    {
        return $this->fin->modify('+1 day');
    }

    public function etiqueta(): string
    {
        if ($this->periodicidad === PeriodicidadPago::MENSUAL) {
            return self::MESES[(int) $this->inicio->format('n')].' '.$this->inicio->format('Y');
        }

        return 'Semana '.$this->fin->format('W');
    }

    public function rango(): string
    {
        return $this->inicio->format('d/m/Y').' – '.$this->fin->format('d/m/Y');
    }

    public static function etiquetaDe(PeriodicidadPago $periodicidad, DateTimeImmutable $inicio): string
    {
        return $periodicidad === PeriodicidadPago::SEMANAL
            ? 'Semana '.$inicio->modify('+6 days')->format('W')
            : self::mesQueContiene($inicio)->etiqueta();
    }
}
