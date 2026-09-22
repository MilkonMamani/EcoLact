<?php

namespace App\Application\Pago;

/**
 * Datos del comprobante individual "Pago semanal a proveedor": lo mismo que se
 * ve en la hoja de la pantalla, en la vista previa de impresión y en el papel.
 * Al confirmar el pago se guarda una copia (toArray) en pagos.comprobante_snapshot.
 */
final class ComprobantePagoProveedor
{
    /**
     * @param  array<int, array{producto: string, presentacion: string, cantidad: string, precioUnitario: float, importe: float}>  $descuentos
     */
    public function __construct(
        public readonly int $obligacionId,
        public readonly int $proveedorId,
        public readonly string $proveedorNombre,
        public readonly string $dni,
        public readonly string $codigo,
        public readonly string $zona,
        public readonly string $semanaEtiqueta,
        public readonly string $periodoInicio,
        public readonly string $periodoFin,
        public readonly string $rango,
        public readonly float $litros,
        public readonly float $precioLitro,
        public readonly float $importeLeche,
        public readonly float $penalidad,
        public readonly float $penalidadLitros,
        public readonly string $detallePenalidad,
        public readonly float $subtotalLeche,
        public readonly array $descuentos,
        public readonly float $subtotalDescuentos,
        public readonly float $totalFinal,
        public readonly string $estado,
        public readonly ?string $pagadoEl,
        public readonly int $anioFirma,
        public readonly ?string $pagadoPor = null,
    ) {}

    public function estaPagado(): bool
    {
        return $this->estado === 'PAGADO';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos, ?string $estado = null, ?string $pagadoEl = null, ?string $pagadoPor = null): self
    {
        return new self(
            obligacionId: (int) $datos['obligacionId'],
            proveedorId: (int) $datos['proveedorId'],
            proveedorNombre: (string) $datos['proveedorNombre'],
            dni: (string) $datos['dni'],
            codigo: (string) $datos['codigo'],
            zona: (string) $datos['zona'],
            semanaEtiqueta: (string) $datos['semanaEtiqueta'],
            periodoInicio: (string) $datos['periodoInicio'],
            periodoFin: (string) $datos['periodoFin'],
            rango: (string) $datos['rango'],
            litros: (float) $datos['litros'],
            precioLitro: (float) $datos['precioLitro'],
            importeLeche: (float) $datos['importeLeche'],
            penalidad: (float) $datos['penalidad'],
            penalidadLitros: (float) $datos['penalidadLitros'],
            detallePenalidad: (string) $datos['detallePenalidad'],
            subtotalLeche: (float) $datos['subtotalLeche'],
            descuentos: (array) $datos['descuentos'],
            subtotalDescuentos: (float) $datos['subtotalDescuentos'],
            totalFinal: (float) $datos['totalFinal'],
            estado: $estado ?? (string) $datos['estado'],
            pagadoEl: $pagadoEl ?? ($datos['pagadoEl'] ?? null),
            anioFirma: (int) $datos['anioFirma'],
            pagadoPor: $pagadoPor ?? ($datos['pagadoPor'] ?? null),
        );
    }
}
