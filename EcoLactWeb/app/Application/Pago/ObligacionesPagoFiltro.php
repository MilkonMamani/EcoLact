<?php

namespace App\Application\Pago;

use App\Domain\Shared\Enums\EstadoObligacionPago;

/** Filtros del listado de Pagos, ya normalizados (valores inválidos se ignoran). */
final class ObligacionesPagoFiltro
{
    private function __construct(
        public readonly ?string $busqueda,
        public readonly ?string $tipo,
        public readonly ?string $periodo,
        public readonly ?EstadoObligacionPago $estado,
        public readonly int $porPagina,
    ) {}

    /**
     * $tipo es "PROVEEDOR" o el nombre de un rol de la tabla roles; un nombre
     * que no exista simplemente no devuelve resultados.
     */
    public static function resolver(?string $busqueda = null, ?string $tipo = null, ?string $periodo = null, ?string $estado = null, int $porPagina = 15): self
    {
        $busqueda = $busqueda !== null && trim($busqueda) !== '' ? trim($busqueda) : null;
        $tipo = $tipo !== null && preg_match('/^[A-Z0-9_]{1,50}$/', $tipo) === 1 ? $tipo : null;
        $periodo = $periodo !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo) === 1 ? $periodo : null;

        return new self($busqueda, $tipo, $periodo, $estado !== null ? EstadoObligacionPago::tryFrom($estado) : null, $porPagina);
    }
}
