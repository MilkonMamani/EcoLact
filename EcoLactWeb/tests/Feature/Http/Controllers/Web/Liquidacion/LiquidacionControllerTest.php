<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Http\Controllers\Web\Liquidacion\LiquidacionController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Ya no existe una pantalla de Liquidaciones para el administrador (el flujo es
// Configuración de pagos -> Pagos). Solo se conserva la hoja imprimible (sobre de
// pago), que se abre desde el detalle de un pago de proveedor.

it('imprimir renderiza la hoja de la liquidación con el total a pagar en efectivo', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entrega->id);

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    $response = $this->actingAs($admin)->get(route('admin.liquidaciones.imprimir', $liquidacion->id()));

    $response->assertOk();
    $response->assertSee('Liquidación #'.$liquidacion->id());
    $response->assertSee('DNI '.$proveedor->dni);
    $response->assertSee('TOTAL A PAGAR EN EFECTIVO');
});

it('imprimir aplica la Policy: un ACOPIADOR no puede imprimir una liquidación (403)', function () {
    Route::get('/_test/liquidaciones/{liquidacion}/imprimir-403', [LiquidacionController::class, 'imprimir']);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entrega->id);

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: new DateTimeImmutable('2026-02-05'),
        fechaFin: new DateTimeImmutable('2026-02-11'),
        generadoPorUserId: $admin->id,
    );

    $response = $this->actingAs($acopiador->user)->get('/_test/liquidaciones/'.$liquidacion->id().'/imprimir-403');

    $response->assertForbidden();
});
