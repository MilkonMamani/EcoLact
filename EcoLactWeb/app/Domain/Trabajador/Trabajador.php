<?php

namespace App\Domain\Trabajador;

use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;

/**
 * Identidad laboral de una persona del personal remunerable: proyección
 * derivada de users + rol + ficha (o solo users para roles sin ficha, hoy
 * ADMINISTRADOR), sincronizada por SincronizarTrabajadores. Es el ancla de
 * asistencia (jornadas_personal), QR y nómina.
 *
 * La vigencia (¿le corresponde trabajar tal día?) sale de sus periodos de
 * actividad (trabajador_periodos_actividad), nunca solo de su estado actual:
 * un trabajador reactivado no "corresponde" en los días en que estuvo
 * inactivo. Sin periodos cargados se usa el intervalo [fechaIngreso, fechaSalida].
 */
final class Trabajador
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $userId,
        private readonly ?string $dni,
        private readonly string $nombres,
        private readonly string $apellidos,
        private readonly ?string $telefono,
        private readonly EstadoUsuario $estado,
        private readonly DateTimeImmutable $fechaIngreso,
        private readonly ?DateTimeImmutable $fechaSalida,
        private readonly ?string $qrToken,
        /** @var PeriodoActividad[] */
        private readonly array $periodos = [],
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function dni(): ?string
    {
        return $this->dni;
    }

    public function nombres(): string
    {
        return $this->nombres;
    }

    public function apellidos(): string
    {
        return $this->apellidos;
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    public function telefono(): ?string
    {
        return $this->telefono;
    }

    public function estado(): EstadoUsuario
    {
        return $this->estado;
    }

    public function fechaIngreso(): DateTimeImmutable
    {
        return $this->fechaIngreso;
    }

    public function fechaSalida(): ?DateTimeImmutable
    {
        return $this->fechaSalida;
    }

    public function qrToken(): ?string
    {
        return $this->qrToken;
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoUsuario::ACTIVO;
    }

    /**
     * Periodos de actividad ordenados por inicio.
     *
     * @return PeriodoActividad[]
     */
    public function periodos(): array
    {
        return $this->periodos !== [] ? $this->periodos : [new PeriodoActividad($this->fechaIngreso, $this->fechaSalida)];
    }

    /** Si trae periodos de actividad guardados (false = identidad anterior al historial, usa el fallback). */
    public function tienePeriodosRegistrados(): bool
    {
        return $this->periodos !== [];
    }

    /** Periodo abierto (sigue activo), si existe. */
    public function periodoAbierto(): ?PeriodoActividad
    {
        foreach ($this->periodos() as $periodo) {
            if ($periodo->estaAbierto()) {
                return $periodo;
            }
        }

        return null;
    }

    /** Vigente esa fecha: la fecha cae dentro de alguno de sus periodos de actividad. */
    public function estaVigenteEn(DateTimeImmutable $fecha): bool
    {
        foreach ($this->periodos() as $periodo) {
            if ($periodo->contiene($fecha)) {
                return true;
            }
        }

        return false;
    }

    /** Estuvo vigente durante TODO el rango [inicio, fin] (p.ej. un mes calendario completo, sin huecos de inactividad). */
    public function estuvoVigenteTodoElRango(DateTimeImmutable $inicio, DateTimeImmutable $fin): bool
    {
        $dias = (int) (new DateTimeImmutable($inicio->format('Y-m-d')))->diff(new DateTimeImmutable($fin->format('Y-m-d')))->days + 1;

        return $this->diasVigentesEn($inicio, $fin) === $dias;
    }

    /** Días calendario del rango [inicio, fin] en los que estuvo vigente (suma de sus periodos de actividad). */
    public function diasVigentesEn(DateTimeImmutable $inicio, DateTimeImmutable $fin): int
    {
        return array_sum(array_map(fn (PeriodoActividad $periodo): int => $periodo->diasEn($inicio, $fin), $this->periodos()));
    }
}
