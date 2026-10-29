<?php

namespace App\Application\Caja;

use App\Domain\Shared\Enums\CategoriaMovimientoCaja;
use App\Domain\Shared\Enums\TipoMovimientoCaja;
use DateTimeImmutable;

/**
 * Un movimiento tal como lo pintan el historial y su panel de detalle.
 *
 * `compraId` y `comprobanteId` (solo en salidas de Compras y gastos) enlazan
 * con la compra de origen y con su primer comprobante, por ids guardados:
 * Caja no guarda una copia del archivo.
 */
final readonly class MovimientoCajaFila
{
    /**
     * @param  array<string, string>  $detalle
     */
    public function __construct(
        public int $id,
        public TipoMovimientoCaja $tipo,
        public CategoriaMovimientoCaja $categoria,
        public float $monto,
        public string $concepto,
        public ?string $descripcion,
        public string $referencia,
        public array $detalle,
        public string $referenciaTipo,
        public int $referenciaId,
        public string $registradoPor,
        public DateTimeImmutable $fecha,
        public ?int $compraId = null,
        public ?int $comprobanteId = null,
    ) {}

    public function esEntrada(): bool
    {
        return $this->tipo === TipoMovimientoCaja::ENTRADA;
    }

    /** "+ S/ 10,000.00" o "- S/ 320.00". */
    public function montoConSigno(): string
    {
        return ($this->esEntrada() ? '+' : '-').' S/ '.number_format($this->monto, 2);
    }
}
