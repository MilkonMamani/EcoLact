<?php

namespace App\Application\Pago;

/**
 * Fila de la pantalla "Asistencia de hoy": un trabajador y su marcación de
 * una fecha (o su ausencia de marcación). $rol ya es el nombre legible
 * (Role::etiquetaDe) y $origen el código de OrigenJornada, para que la vista
 * elija el ícono sin conocer enums.
 */
final readonly class FilaAsistenciaDelDia
{
    public function __construct(
        public int $trabajadorId,
        public int $userId,
        public string $nombre,
        public string $iniciales,
        public string $rol,
        public ?string $dni,
        public ?string $estado,
        public ?string $estadoEtiqueta,
        public ?string $horaMarcacion,
        public ?string $origenEtiqueta,
        public bool $esHoy,
        public ?int $jornadaId,
        public ?string $origen = null,
    ) {}

    public function estaPresente(): bool
    {
        return $this->estado === 'PRESENTE';
    }

    public function tieneFalta(): bool
    {
        return $this->estado === 'FALTA';
    }

    /** Sin jornada ese día: hoy es "pendiente" (aún puede marcar); en una fecha pasada, "sin registro". */
    public function sinRegistro(): bool
    {
        return $this->estado === null;
    }
}
