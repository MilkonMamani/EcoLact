<?php

namespace App\Domain\Shared\Enums;

/**
 * De qué módulo viene el dinero de la caja. Cada categoría tiene un único
 * sentido: las ventas y el saldo inicial siempre entran y el resto siempre
 * sale. SALDO_INICIAL es el dinero con que arrancó la caja (uno solo).
 * INSUMOS y GASTOS son los pagos de Compras y gastos: INSUMOS para las
 * compras de insumos de producción y GASTOS para el resto (limpieza,
 * servicios, equipos...), según la categoría de la compra.
 */
enum CategoriaMovimientoCaja: string
{
    case VENTA = 'VENTA';
    case PROVEEDOR = 'PROVEEDOR';
    case TRABAJADOR = 'TRABAJADOR';
    case INSUMOS = 'INSUMOS';
    case GASTOS = 'GASTOS';
    case SALDO_INICIAL = 'SALDO_INICIAL';

    public function etiqueta(): string
    {
        return match ($this) {
            self::VENTA => 'Venta',
            self::PROVEEDOR => 'Proveedor',
            self::TRABAJADOR => 'Trabajador',
            self::INSUMOS => 'Insumos',
            self::GASTOS => 'Gasto',
            self::SALDO_INICIAL => 'Saldo inicial',
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
            self::GASTOS => 'Gastos',
            self::SALDO_INICIAL => 'Saldo inicial',
        };
    }

    public function tipo(): TipoMovimientoCaja
    {
        return in_array($this, [self::VENTA, self::SALDO_INICIAL], true) ? TipoMovimientoCaja::ENTRADA : TipoMovimientoCaja::SALIDA;
    }
}
