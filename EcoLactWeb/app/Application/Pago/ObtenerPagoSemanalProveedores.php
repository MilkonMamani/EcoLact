<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PagoSemanalProveedoresQuery;
use DateTimeImmutable;

/** Datos de la pantalla "Pago semanal a proveedores". */
final class ObtenerPagoSemanalProveedores
{
    public function __construct(private readonly PagoSemanalProveedoresQuery $consulta) {}

    /**
     * @return array<string, mixed> ver PagoSemanalProveedoresQuery::listado()
     */
    public function execute(PagoSemanalFiltro $filtro, ?DateTimeImmutable $hoy = null): array
    {
        return $this->consulta->listado($filtro, $hoy ?? DateTimeImmutable::createFromInterface(now()));
    }
}
