<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PagoSemanalProveedoresQuery;

/** Comprobantes individuales de pago semanal de los proveedores indicados. */
final class ObtenerComprobantesPagoProveedor
{
    public function __construct(private readonly PagoSemanalProveedoresQuery $consulta) {}

    /**
     * @param  int[]  $obligacionIds
     * @return ComprobantePagoProveedor[]
     */
    public function execute(array $obligacionIds): array
    {
        return $this->consulta->comprobantes(array_values(array_unique(array_map('intval', $obligacionIds))));
    }
}
