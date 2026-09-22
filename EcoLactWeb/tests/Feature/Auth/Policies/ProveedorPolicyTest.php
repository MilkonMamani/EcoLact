<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('un ACOPIADOR puede ver un proveedor de su misma zona', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    expect($acopiador->user->can('view', $proveedor))->toBeTrue();
});

it('un ACOPIADOR NO puede ver un proveedor de otra zona', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedor = fixture_proveedor($zonaA);
    $acopiador = fixture_acopiador($zonaB);

    expect($acopiador->user->can('view', $proveedor))->toBeFalse();
});

it('un PROVEEDOR puede ver su propio registro', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    expect($proveedor->user->can('view', $proveedor))->toBeTrue();
});

it('un PROVEEDOR NO puede ver el registro de otro proveedor', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);

    expect($proveedorA->user->can('view', $proveedorB))->toBeFalse();
});
