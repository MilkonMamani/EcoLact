<?php

namespace App\Application\Pago;

/**
 * Un día del calendario mensual de asistencia de un trabajador. `estado` es
 * uno de: PRESENTE, FALTA (jornada real registrada), SIN_REGISTRO (día ya
 * vigente sin jornada: hoy sin marcar aún, o un hueco anómalo en el pasado —
 * nunca se infiere FALTA desde el frontend), NO_APLICABLE (antes del ingreso
 * o después de la salida del trabajador) o FUTURO.
 */
final readonly class DiaCalendarioTrabajador
{
    public function __construct(
        public string $fechaIso,
        public int $diaNumero,
        public string $estado,
        public ?string $horaMarcacion,
        public ?string $origenEtiqueta,
        public ?string $observacion,
        public ?int $jornadaId,
        public bool $esHoy,
    ) {}
}
