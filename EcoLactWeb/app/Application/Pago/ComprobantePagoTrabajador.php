<?php

namespace App\Application\Pago;

/** Comprobante individual de pago de un trabajador: la misma hoja para la vista previa y la página imprimible. */
final class ComprobantePagoTrabajador
{
    public function __construct(
        public readonly int $obligacionId,
        public readonly int $userId,
        public readonly string $nombre,
        public readonly ?string $dni,
        public readonly string $rol,
        public readonly string $periodoEtiqueta,
        public readonly string $periodoInicio,
        public readonly string $periodoRango,
        public readonly int $diasTrabajados,
        public readonly int $faltas,
        public readonly float $valorDia,
        public readonly float $sueldoBase,
        public readonly float $acumulado,
        public readonly float $descuentoPorFaltas,
        public readonly bool $esMesCompleto,
        public readonly ?string $fechaIngresoEtiqueta,
        public readonly float $bonificacion,
        public readonly float $descuento,
        public readonly float $total,
        public readonly string $estado,
        public readonly ?string $observacion,
        public readonly ?string $pagadoEl,
        public readonly ?string $pagadoPor,
        public readonly ?string $metodoPago,
        public readonly int $anioFirma,
    ) {}

    public function estaPagado(): bool
    {
        return $this->estado === 'PAGADO';
    }
}
