<?php

use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ReglaCalidadModel;
use App\Infrastructure\Persistence\Models\ReglaPagoCalidadModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('ADMINISTRADOR puede acceder a la gestión de proveedores', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    expect($admin->can('create', ProveedorModel::class))->toBeTrue();
    expect($admin->can('update', fixture_proveedor(fixture_zona())))->toBeTrue();
});

it('ADMINISTRADOR puede gestionar la configuración de calidad (parámetros y reglas)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    expect($admin->can('create', ParametroCalidadModel::class))->toBeTrue();
    expect($admin->can('create', ReglaCalidadModel::class))->toBeTrue();
});

it('ADMINISTRADOR puede gestionar precios de leche y reglas de pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    expect($admin->can('create', PrecioLecheModel::class))->toBeTrue();
    expect($admin->can('create', ReglaPagoCalidadModel::class))->toBeTrue();
});

it('un ACOPIADOR no puede configurar el precio de leche', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    expect($acopiador->user->can('create', PrecioLecheModel::class))->toBeFalse();
    expect($acopiador->user->can('create', ReglaPagoCalidadModel::class))->toBeFalse();
});

it('un PROVEEDOR no puede acceder a ninguna configuración administrativa', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $user = $proveedor->user;

    expect($user->can('create', ParametroCalidadModel::class))->toBeFalse();
    expect($user->can('create', ReglaCalidadModel::class))->toBeFalse();
    expect($user->can('create', PrecioLecheModel::class))->toBeFalse();
    expect($user->can('create', ReglaPagoCalidadModel::class))->toBeFalse();
    expect($user->can('create', ZonaModel::class))->toBeFalse();
});
