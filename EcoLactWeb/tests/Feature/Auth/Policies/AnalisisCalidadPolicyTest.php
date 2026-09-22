<?php

use App\Application\Calidad\FinalizarAnalisisCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('un PROVEEDOR puede ver su propio análisis de calidad', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $admin = fixture_usuario('ADMINISTRADOR');
    $analisis = fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    expect($proveedor->user->can('view', $analisis))->toBeTrue();
});

it('un PROVEEDOR no puede modificar ningún análisis de calidad, ni siquiera el propio', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $admin = fixture_usuario('ADMINISTRADOR');
    $analisis = fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    expect($proveedor->user->can('update', $analisis))->toBeFalse();
});

it('un análisis CONFIRMADO sigue siendo inmutable aunque el usuario sea ADMINISTRADOR', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $admin = fixture_usuario('ADMINISTRADOR');
    $analisis = fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    // Defensa en la capa de autorización: Gate::before NO concede acceso
    // ciego aquí, la Policy deniega explícitamente por estar CONFIRMADO.
    expect($admin->can('update', $analisis))->toBeFalse();

    // Defensa en profundidad: aunque alguien se saltara la Policy, Domain
    // sigue rechazando la operación real (FinalizarAnalisisCalidad ya
    // confirmado no puede volver a confirmarse).
    app(FinalizarAnalisisCalidad::class)->execute($analisis->id, $admin->id);
})->throws(DomainException::class, 'Un análisis de calidad confirmado es inmutable y no puede modificarse.');
