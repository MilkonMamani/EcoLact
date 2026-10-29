<?php

namespace App\Domain\Compra;

/**
 * Tienda o empresa a la que EcoLact le compra. NO es un proveedor de leche
 * (App\Domain\Proveedor): no entrega leche, no tiene zona ni liquidación.
 */
final class ProveedorComercial
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $razonSocial,
        private readonly ?string $ruc = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function razonSocial(): string
    {
        return $this->razonSocial;
    }

    public function ruc(): ?string
    {
        return $this->ruc;
    }
}
