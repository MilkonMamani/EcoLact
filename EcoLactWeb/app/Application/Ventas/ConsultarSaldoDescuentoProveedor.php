<?php

namespace App\Application\Ventas;

/**
 * Para la previsualización de "Descontar de pago semanal": cuánto puede
 * comprar todavía el proveedor a cuenta de su pago del periodo en curso.
 * Es la misma estimación con la que RegistrarVentaProducto valida, pero de
 * solo lectura (no crea el periodo si aún no existe).
 */
final class ConsultarSaldoDescuentoProveedor
{
    public function __construct(
        private readonly PeriodoDescuentoActual $periodoActual,
        private readonly EstimarSaldoDisponibleProveedor $estimarSaldo,
    ) {}

    public function execute(int $proveedorId): float
    {
        return $this->estimarSaldo->execute($proveedorId, $this->periodoActual->consultar());
    }
}
