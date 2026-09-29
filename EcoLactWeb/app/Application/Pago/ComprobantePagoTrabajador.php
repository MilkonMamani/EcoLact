<?php

namespace App\Application\Pago;

/**
 * Comprobante individual de pago de un trabajador (hoja A4 imprimible). Todos los
 * montos vienen del snapshot de la obligación (obligaciones_pago); esta clase solo
 * agrupa los datos del documento y deriva su número, nunca recalcula dinero.
 */
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
        /** Zona del trabajador (hoy solo los acopiadores tienen zona). */
        public readonly ?string $zona,
        /** d/m/Y: fecha en que se registró el pago; si aún no se paga, la fecha en que se genera la vista previa. */
        public readonly string $fechaEmision,
        /** d/m/Y, H:i (hora del negocio): cabecera breve del documento impreso. */
        public readonly string $fechaHoraEmision,
        public readonly ?string $numeroOperacion,
    ) {}

    public function estaPagado(): bool
    {
        return $this->estado === 'PAGADO';
    }

    /**
     * Número del comprobante: P-{año del periodo}-{id de la obligación, 4 dígitos}.
     * La obligación es única por trabajador y mes (clave beneficiario+periodo), así
     * que el número es estable: la vista previa y el comprobante pagado del mismo
     * mes siempre muestran el mismo número, sin tabla de correlativos aparte.
     */
    public function numero(): string
    {
        return sprintf('P-%s-%04d', substr($this->periodoInicio, 0, 4), $this->obligacionId);
    }
}
