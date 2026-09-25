<?php

namespace App\Application\Pago;

/** Fila de la tabla "Control de asistencia": un trabajador y su marcación de una fecha (o su ausencia de marcación). */
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
    ) {}
}
