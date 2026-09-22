<?php

namespace Tests\Unit\Domain\Pago;

use App\Domain\Pago\Liquidacion;
use App\Domain\Shared\Enums\EstadoLiquidacion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LiquidacionTest extends TestCase
{
    public function test_representa_correctamente_sus_totales(): void
    {
        $liquidacion = new Liquidacion(
            id: null,
            periodoId: 1,
            proveedorId: 2,
            totalLitrosEntregados: 500.0,
            totalLitrosPagables: 480.0,
            subtotal: 960.00,
            totalAjustes: -40.00,
            totalPagar: 920.00,
            estado: EstadoLiquidacion::PENDIENTE,
            fechaCalculo: new DateTimeImmutable('2026-09-01'),
            generadoPorUserId: 1,
        );

        $this->assertSame(920.00, $liquidacion->totalPagar());
        $this->assertSame(920.00, $liquidacion->totalPagarCalculado());
        $this->assertTrue($liquidacion->coincideConTotalCalculado());
    }

    public function test_detecta_una_inconsistencia_en_el_total_a_pagar(): void
    {
        $liquidacion = new Liquidacion(
            id: null,
            periodoId: 1,
            proveedorId: 2,
            totalLitrosEntregados: 500.0,
            totalLitrosPagables: 480.0,
            subtotal: 960.00,
            totalAjustes: -40.00,
            totalPagar: 999.00,
            estado: EstadoLiquidacion::PENDIENTE,
            fechaCalculo: new DateTimeImmutable('2026-09-01'),
            generadoPorUserId: 1,
        );

        $this->assertFalse($liquidacion->coincideConTotalCalculado());
    }
}
