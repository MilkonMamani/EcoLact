<?php

use App\Application\Zona\ActualizarZona;
use App\Application\Zona\RegistrarZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('actualiza el nombre y la descripción de una zona', function () {
    $zona = app(RegistrarZona::class)->execute(nombre: 'Zona Original', descripcion: 'Desc original', estado: 'ACTIVO');

    $actualizada = app(ActualizarZona::class)->execute(
        zonaId: $zona->id(),
        nombre: 'Zona Renombrada',
    );

    expect($actualizada->nombre())->toBe('Zona Renombrada');
    expect($actualizada->descripcion())->toBe('Desc original');
});

it('no permite desactivar una zona que tiene un acopiador activo asignado', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();
    fixture_acopiador($zona);

    app(ActualizarZona::class)->execute(zonaId: $zona->id, estado: 'INACTIVO');
})->throws(DomainException::class, 'No se puede desactivar la zona: todavía tiene acopiadores o proveedores activos asignados.');

it('no permite desactivar una zona que tiene un proveedor activo asignado', function () {
    fixture_rol('PROVEEDOR');
    $zona = fixture_zona();
    fixture_proveedor($zona);

    app(ActualizarZona::class)->execute(zonaId: $zona->id, estado: 'INACTIVO');
})->throws(DomainException::class, 'No se puede desactivar la zona: todavía tiene acopiadores o proveedores activos asignados.');

it('permite desactivar una zona sin acopiadores ni proveedores activos', function () {
    $zona = app(RegistrarZona::class)->execute(nombre: 'Zona Vacía', descripcion: null, estado: 'ACTIVO');

    $actualizada = app(ActualizarZona::class)->execute(zonaId: $zona->id(), estado: 'INACTIVO');

    expect($actualizada->estado())->toBe('INACTIVO');
});

it('rechaza actualizar una zona que no existe', function () {
    app(ActualizarZona::class)->execute(zonaId: 999999, nombre: 'X');
})->throws(DomainException::class, 'La zona indicada no existe.');
