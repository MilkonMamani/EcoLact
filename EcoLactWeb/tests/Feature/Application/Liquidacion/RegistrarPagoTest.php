<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\RegistrarPago;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Shared\Enums\EstadoLiquidacion;
use App\Domain\Shared\Enums\EstadoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra un pago válido y marca la liquidación como pagada al cubrir el saldo', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entrega->id);

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    $pago = app(RegistrarPago::class)->execute(
        liquidacionId: $liquidacion->id(),
        monto: 51.0,
        registradoPorUserId: $admin->id,
    );

    expect($pago->id())->not->toBeNull();
    expect($pago->estado())->toBe(EstadoPago::REGISTRADO);
    expect($pago->monto())->toBe(51.0);

    $liquidacionActualizada = app(LiquidacionRepository::class)->buscarPorId($liquidacion->id());
    expect($liquidacionActualizada->estado())->toBe(EstadoLiquidacion::PAGADA);
});

it('un pago parcial deja la liquidación en estado PARCIALMENTE_PAGADA', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entregaJueves = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaJueves, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaJueves->id);

    $entregaViernes = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-06'));
    fixture_analisis_confirmado($entregaViernes, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaViernes->id);

    // Total a pagar: 30*1.70 + 30*1.70 = 102.00
    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    app(RegistrarPago::class)->execute(
        liquidacionId: $liquidacion->id(),
        monto: 40.0,
        registradoPorUserId: $admin->id,
    );

    $liquidacionParcial = app(LiquidacionRepository::class)->buscarPorId($liquidacion->id());
    expect($liquidacionParcial->estado())->toBe(EstadoLiquidacion::PARCIALMENTE_PAGADA);

    app(RegistrarPago::class)->execute(
        liquidacionId: $liquidacion->id(),
        monto: 62.0,
        registradoPorUserId: $admin->id,
    );

    $liquidacionCompleta = app(LiquidacionRepository::class)->buscarPorId($liquidacion->id());
    expect($liquidacionCompleta->estado())->toBe(EstadoLiquidacion::PAGADA);
});

it('no permite registrar un pago que exceda el saldo pendiente de la liquidación', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entrega->id);

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    app(RegistrarPago::class)->execute(
        liquidacionId: $liquidacion->id(),
        monto: 999.0,
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'El monto del pago supera el saldo pendiente de la liquidación.');
