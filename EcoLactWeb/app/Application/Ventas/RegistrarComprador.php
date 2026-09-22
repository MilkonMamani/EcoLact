<?php

namespace App\Application\Ventas;

use App\Domain\Ventas\Comprador;
use App\Domain\Ventas\CompradorRepository;

/**
 * Registra un comprador que no es proveedor (mayorista o público que se
 * quiere identificar), para trazabilidad de sus compras.
 */
final class RegistrarComprador
{
    public function __construct(
        private readonly CompradorRepository $compradores,
    ) {}

    public function execute(
        string $nombre,
        string $tipoCliente,
        ?string $documento = null,
        ?string $telefono = null,
    ): Comprador {
        return $this->compradores->guardar(new Comprador(
            id: null,
            nombre: $nombre,
            documento: $documento,
            telefono: $telefono,
            tipoCliente: $tipoCliente,
            estado: 'ACTIVO',
        ));
    }
}
