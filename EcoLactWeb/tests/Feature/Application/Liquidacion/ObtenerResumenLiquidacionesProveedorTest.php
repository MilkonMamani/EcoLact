<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\ObtenerResumenLiquidacionesProveedor;
use App\Application\Liquidacion\RegistrarPago;
use App\Application\Liquidacion\ValorizarEntrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('calcula por cobrar, pagado, última liquidación y el total de liquidaciones de un proveedor', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entregaA = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaA, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaA->id);
    $liquidacionPagada = app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);
    app(RegistrarPago::class)->execute($liquidacionPagada->id(), $liquidacionPagada->totalPagar(), $admin->id);

    $entregaB = fixture_entrega($proveedor, $acopiador, 20.0, new DateTimeImmutable('2026-02-12'));
    fixture_analisis_confirmado($entregaB, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaB->id);
    $liquidacionPendiente = app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable('2026-02-12'), new DateTimeImmutable('2026-02-18'), $admin->id);

    $resumen = app(ObtenerResumenLiquidacionesProveedor::class)->execute($proveedor->id);

    expect($resumen->totalLiquidaciones)->toBe(2);
    expect($resumen->pagado)->toBe(round($liquidacionPagada->totalPagar(), 2));
    expect($resumen->porCobrar)->toBe(round($liquidacionPendiente->totalPagar(), 2));
    expect($resumen->ultimaLiquidacion?->id())->toBe($liquidacionPendiente->id());
});

it('devuelve un resumen vacío cuando el proveedor no tiene liquidaciones', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $resumen = app(ObtenerResumenLiquidacionesProveedor::class)->execute($proveedor->id);

    expect($resumen->ultimaLiquidacion)->toBeNull();
    expect($resumen->porCobrar)->toBe(0.0);
    expect($resumen->pagado)->toBe(0.0);
    expect($resumen->totalLiquidaciones)->toBe(0);
});
