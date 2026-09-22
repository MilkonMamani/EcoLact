<?php

namespace App\Application\Acopio;

use App\Domain\Entrega\Entrega;
use App\Domain\Entrega\EntregaRepository;

final class ObtenerEntregas
{
    public function __construct(
        private readonly EntregaRepository $entregas,
    ) {}

    /**
     * Con $zonaId, acota a las entregas de esa zona (uso de ACOPIADOR,
     * siempre con su propia zona); sin él, lista todas (uso de
     * ADMINISTRADOR). Nunca se usa para PROVEEDOR: ese rol tiene su propio
     * Use Case ObtenerEntregasProveedor, acotado por proveedorId.
     *
     * @return Entrega[]
     */
    public function execute(?int $zonaId = null): array
    {
        return $zonaId !== null
            ? $this->entregas->listarPorZona($zonaId)
            : $this->entregas->listar();
    }
}
