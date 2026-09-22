<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\RegistrarPago;
use App\Application\Liquidacion\ValorizarEntrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de mis liquidaciones muestra el resumen y el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entrega->id);
    app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.liquidaciones.index'));

    $response->assertOk();
    $response->assertSee('Por cobrar');
    $response->assertSee('Pagado');
});

it('el detalle de una liquidación pagada muestra el historial de pagos dentro de la propia pantalla', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entrega->id);
    $liquidacion = app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);
    app(RegistrarPago::class)->execute($liquidacion->id(), $liquidacion->totalPagar(), $admin->id);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.liquidaciones.show', $liquidacion->id()));

    $response->assertOk();
    $response->assertSee('¿Cómo se calculó?');
    $response->assertSee('Entregas incluidas');
    $response->assertSee('Historial de pagos');
    $response->assertSee('sobre cerrado');
});

it('un proveedor no puede ver la liquidación de otro proveedor (403)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedorA, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entrega->id);
    $liquidacion = app(GenerarLiquidacion::class)->execute($proveedorA->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $this->actingAs($proveedorB->user)->get(route('proveedor.liquidaciones.show', $liquidacion->id()))->assertForbidden();
});
