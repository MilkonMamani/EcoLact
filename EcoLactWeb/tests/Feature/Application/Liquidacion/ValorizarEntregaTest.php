<?php

use App\Application\Liquidacion\ValorizarEntrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('valoriza una entrega según el precio base y la regla de pago por calidad configurados', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $acopiador->user_id);

    $valorizacion = app(ValorizarEntrega::class)->execute($entrega->id);

    expect($valorizacion->litrosPagables())->toBe(30.0);
    expect($valorizacion->precioFinalLitro())->toBe(1.7);
    expect($valorizacion->importeTotal())->toBe(51.0);
});

it('dos entregas con distinta calidad en el mismo periodo producen valorizaciones distintas', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    fixture_regla_pago('OBSERVADA', 'MONTO', -0.30, new DateTimeImmutable('2026-01-01'));

    $entregaJueves = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaJueves, 'ACEPTADA', $acopiador->user_id);

    $entregaViernes = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-06'));
    fixture_analisis_confirmado($entregaViernes, 'OBSERVADA', $acopiador->user_id);

    $valorizacionJueves = app(ValorizarEntrega::class)->execute($entregaJueves->id);
    $valorizacionViernes = app(ValorizarEntrega::class)->execute($entregaViernes->id);

    expect($valorizacionJueves->importeTotal())->toBe(51.0);
    expect($valorizacionViernes->importeTotal())->toBe(42.0);
    expect($valorizacionJueves->importeTotal())->not->toBe($valorizacionViernes->importeTotal());
});

it('una entrega RECHAZADA queda con litros pagables en cero: no entra físicamente a la planta', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('RECHAZADA', 'MONTO', -1.70, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'RECHAZADA', $acopiador->user_id);

    $valorizacion = app(ValorizarEntrega::class)->execute($entrega->id);

    expect($valorizacion->litrosPagables())->toBe(0.0);
    expect($valorizacion->litrosEntregados())->toBe(30.0);
    expect($valorizacion->importeTotal())->toBe(0.0);
});

it('no permite valorizar una entrega que no tiene un análisis de calidad confirmado', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    app(ValorizarEntrega::class)->execute($entrega->id);
})->throws(DomainException::class, 'La entrega no tiene un análisis de calidad confirmado.');
