<?php

namespace App\Domain\Shared\Enums;

/**
 * De qué módulo viene el dinero de la caja. Cada categoría tiene un único
 * sentido: las ventas siempre entran y el resto siempre sale.
 */
enum CategoriaMovimientoCaja: string
{
    case VENTA = 'VENTA';
    case PROVEEDOR = 'PROVEEDOR';
    case TRABAJADOR = 'TRABAJADOR';
    case INSUMOS = 'INSUMOS';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VENTA => 'Venta',
            self::PROVEEDOR => 'Proveedor',
            self::TRABAJADOR => 'Trabajador',
            self::INSUMOS => 'Insumos',
        };
    }

    /** Etiqueta del filtro de categorías ("Ventas", "Proveedores"...). */
    public function etiquetaPlural(): string
    {
        return match ($this) {
            self::VENTA => 'Ventas',
            self::PROVEEDOR => 'Proveedores',
            self::TRABAJADOR => 'Trabajadores',
            self::INSUMOS => 'Insumos',
        };
    }

    public function tipo(): TipoMovimientoCaja
    {
        return $this === self::VENTA ? TipoMovimientoCaja::ENTRADA : TipoMovimientoCaja::SALIDA;
    }
}
