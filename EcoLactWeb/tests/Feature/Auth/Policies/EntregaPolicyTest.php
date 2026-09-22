<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('un ACOPIADOR puede consultar una entrega de su zona', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    expect($acopiador->user->can('view', $entrega))->toBeTrue();
});

it('un ACOPIADOR NO puede consultar una entrega de otra zona', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedor = fixture_proveedor($zonaA);
    $acopiadorZonaA = fixture_acopiador($zonaA);
    $entrega = fixture_entrega($proveedor, $acopiadorZonaA, 30.0);

    $acopiadorZonaB = fixture_acopiador($zonaB);

    expect($acopiadorZonaB->user->can('view', $entrega))->toBeFalse();
});

it('un PROVEEDOR puede ver su propia entrega', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    expect($proveedor->user->can('view', $entrega))->toBeTrue();
});

it('un PROVEEDOR NO puede ver una entrega ajena', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedorA, $acopiador, 30.0);

    expect($proveedorB->user->can('view', $entrega))->toBeFalse();
});
