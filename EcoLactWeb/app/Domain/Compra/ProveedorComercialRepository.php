<?php

namespace App\Domain\Compra;

interface ProveedorComercialRepository
{
    /**
     * El proveedor con ese RUC o, sin RUC, con esa razón social (sin
     * distinguir mayúsculas); si no existe, lo crea. Así la misma tienda no
     * se repite cada vez que se le compra.
     */
    public function buscarOCrear(string $razonSocial, ?string $ruc): ProveedorComercial;

    /**
     * @return list<ProveedorComercial> los activos, por razón social
     */
    public function listarActivos(): array;
}
