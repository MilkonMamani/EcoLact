<?php

namespace App\Application\Pago;

/**
 * Fila de la tabla y datos del panel de "Pagos a trabajadores". Normalmente es la
 * obligación de pago del mes ($id = obligación). Si el rol del trabajador no tiene
 * sueldo configurado no existe obligación: la fila igual se muestra (estado
 * SIN_SUELDO, $id = 0, $trabajadorId = trabajador) con su asistencia real y sin montos.
 */
final readonly class TrabajadorPagoFila
{
    public const SIN_SUELDO = 'SIN_SUELDO';

    public function __construct(
        public int $id,
        public int $userId,
        public string $nombre,
        public string $iniciales,
        public string $rol,
        public ?string $dni,
        public string $periodoEtiqueta,
        public string $periodoRango,
        public string $periodoInicioIso,
        public float $sueldoBase,
        public float $bonificacion,
        public float $descuento,
        public float $total,
        public string $estado,
        public string $estadoEtiqueta,
        public ?string $observacion,
        public ?string $pagadoEl,
        public ?string $metodoPago,
        public ?string $registradoPor,
        public int $diasTrabajados,
        public int $faltas,
        public float $valorDia,
        public float $acumulado,
        public bool $esMesCompleto,
        public ?string $fechaIngresoEtiqueta,
        public ?int $trabajadorId = null,
    ) {}

    public function sinSueldo(): bool
    {
        return $this->estado === self::SIN_SUELDO;
    }

    /** Identificador de la fila en la pantalla (?trabajador=...): la obligación, o "s{trabajador}" si no tiene sueldo. */
    public function clave(): string
    {
        return $this->sinSueldo() ? 's'.$this->trabajadorId : (string) $this->id;
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    public function estaEnCurso(): bool
    {
        return $this->estado === 'EN_CURSO';
    }
}
