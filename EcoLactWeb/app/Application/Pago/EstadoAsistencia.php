<?php

namespace App\Application\Pago;

use App\Domain\Shared\Enums\CondicionAsistencia;
use DateTimeImmutable;

/** Resultado de EstadoAsistenciaActual para un usuario y un día. */
final readonly class EstadoAsistencia
{
    public function __construct(
        public DateTimeImmutable $fecha,
        public bool $requiereAsistencia,
        public CondicionAsistencia $condicion,
        public ?DateTimeImmutable $horaMarcacion = null,
        public ?int $trabajadorId = null,
    ) {}

    public function puedeOperar(): bool
    {
        return $this->condicion->puedeOperar();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'fecha' => $this->fecha->format('Y-m-d'),
            'requiere_asistencia' => $this->requiereAsistencia,
            'puede_operar' => $this->puedeOperar(),
            'estado' => $this->condicion->value,
            'hora_marcacion' => $this->horaMarcacion?->format(DATE_ATOM),
            'hora_cierre' => VentanaAsistencia::HORA_CIERRE,
            'trabajador_id' => $this->trabajadorId,
        ];
    }
}
