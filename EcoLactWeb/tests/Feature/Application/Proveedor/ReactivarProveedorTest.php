<?php

use App\Application\Proveedor\DesactivarProveedor;
use App\Application\Proveedor\ReactivarProveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('reactiva a un proveedor inactivo y reinicia su contador de advertencias', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $proveedor->update(['advertencias_count' => 3]);
    app(DesactivarProveedor::class)->execute($proveedor->id);

    $reactivado = app(ReactivarProveedor::class)->execute($proveedor->id);

    expect($reactivado->estado()->value)->toBe('ACTIVO');
    expect($reactivado->advertenciasCount())->toBe(0);
});

it('no permite reactivar a un proveedor que ya está activo', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    app(ReactivarProveedor::class)->execute($proveedor->id);
})->throws(DomainException::class, 'El proveedor ya se encuentra activo.');
