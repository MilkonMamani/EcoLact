<?php

namespace App\Application\Pago;

/** Fila de la tabla y datos del panel de "Pagar a trabajadores". */
final readonly class TrabajadorPagoFila
{
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
    ) {}

    public function estaPendiente(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    public function estaEnCurso(): bool
    {
        return $this->estado === 'EN_CURSO';
    }
}
