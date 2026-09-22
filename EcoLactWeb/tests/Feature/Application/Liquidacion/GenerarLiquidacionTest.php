<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Domain\Shared\Enums\EstadoLiquidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function glt_prepararProveedorConEntregasValorizadas(): array
{
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    fixture_regla_pago('OBSERVADA', 'MONTO', -0.30, new DateTimeImmutable('2026-01-01'));

    // Periodo EcoLact: jueves 2026-02-05 -> miércoles 2026-02-11.
    $entregaJueves = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaJueves, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaJueves->id);

    $entregaViernes = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-06'));
    fixture_analisis_confirmado($entregaViernes, 'OBSERVADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaViernes->id);

    $entregaSabado = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-07'));
    fixture_analisis_confirmado($entregaSabado, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaSabado->id);

    return [$proveedor, $admin];
}

it('la liquidación semanal aplica UN SOLO precio a todos los litros del periodo cuando hubo una incidencia', function () {
    [$proveedor, $admin] = glt_prepararProveedorConEntregasValorizadas();

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    // La entrega del viernes fue OBSERVADA: TODO el periodo (90 litros, no
    // solo los 30 del viernes) se paga al precio penalizado 1.70 - 0.30 =
    // 1.40, no litros x un precio distinto por cada día.
    expect($liquidacion->totalLitrosEntregados())->toBe(90.0);
    expect($liquidacion->totalLitrosPagables())->toBe(90.0);
    expect($liquidacion->precioLitroAplicado())->toBe(1.4);
    expect($liquidacion->tuvoIncidencia())->toBeTrue();
    expect($liquidacion->subtotal())->toBe(126.0);
    expect($liquidacion->totalPagar())->toBe(126.0);
});

it('sin ninguna incidencia en el periodo se paga el precio base a todos los litros', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    fixture_regla_pago('OBSERVADA', 'MONTO', -0.30, new DateTimeImmutable('2026-01-01'));

    $entregaJueves = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaJueves, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaJueves->id);

    $entregaViernes = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-06'));
    fixture_analisis_confirmado($entregaViernes, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaViernes->id);

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    expect($liquidacion->tuvoIncidencia())->toBeFalse();
    expect($liquidacion->precioLitroAplicado())->toBe(1.7);
    expect($liquidacion->totalLitrosPagables())->toBe(60.0);
    expect($liquidacion->subtotal())->toBe(102.0);
});

it('una entrega RECHAZADA no paga litros ese día, pero sí penaliza el precio de toda la semana', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    fixture_regla_pago('OBSERVADA', 'MONTO', -0.30, new DateTimeImmutable('2026-01-01'));
    fixture_regla_pago('RECHAZADA', 'MONTO', -1.70, new DateTimeImmutable('2026-01-01'));

    $entregaJueves = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaJueves, 'ACEPTADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaJueves->id);

    $entregaViernes = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-06'));
    fixture_analisis_confirmado($entregaViernes, 'RECHAZADA', $acopiador->user_id);
    app(ValorizarEntrega::class)->execute($entregaViernes->id);

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    // 60 litros entregados, pero solo 30 (jueves) son pagables: el viernes
    // rechazado no entra a planta y no se paga.
    expect($liquidacion->totalLitrosEntregados())->toBe(60.0);
    expect($liquidacion->totalLitrosPagables())->toBe(30.0);
    expect($liquidacion->tuvoIncidencia())->toBeTrue();
    expect($liquidacion->precioLitroAplicado())->toBe(1.4);
    expect($liquidacion->subtotal())->toBe(42.0);
});

it('una liquidación recién generada sin pagos queda en estado PENDIENTE', function () {
    [$proveedor, $admin] = glt_prepararProveedorConEntregasValorizadas();

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    expect($liquidacion->estado())->toBe(EstadoLiquidacion::PENDIENTE);
});

it('no duplica la liquidación de un mismo proveedor para el mismo periodo', function () {
    [$proveedor, $admin] = glt_prepararProveedorConEntregasValorizadas();

    $fechaInicio = new DateTimeImmutable('2026-02-05');
    $fechaFin = new DateTimeImmutable('2026-02-11');

    app(GenerarLiquidacion::class)->execute($proveedor->id, $fechaInicio, $fechaFin, $admin->id);
    app(GenerarLiquidacion::class)->execute($proveedor->id, $fechaInicio, $fechaFin, $admin->id);
})->throws(DomainException::class, 'Ya existe una liquidación para este proveedor en el periodo indicado.');
