<?php

namespace App\Application\Pago;

use App\Domain\Shared\Enums\EstadoObligacionPago;

/** Filtros de la pantalla "Pagar a trabajadores", ya normalizados. */
final class TrabajadoresPagoFiltro
{
    private function __construct(
        public readonly ?string $busqueda,
        public readonly ?int $rolId,
        public readonly ?string $periodo,
        public readonly ?EstadoObligacionPago $estado,
        public readonly int $porPagina,
    ) {}

    public static function resolver(
        ?string $busqueda = null,
        ?int $rolId = null,
        ?string $periodo = null,
        ?string $estado = null,
        int $porPagina = 15,
    ): self {
        $busqueda = $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null;
        $periodo = $periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1 ? $periodo : null;

        return new self($busqueda, $rolId, $periodo, $estado !== null ? EstadoObligacionPago::tryFrom($estado) : null, $porPagina);
    }
}
