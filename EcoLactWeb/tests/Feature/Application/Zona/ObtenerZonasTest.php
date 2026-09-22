<?php

use App\Application\Zona\ObtenerZona;
use App\Application\Zona\ObtenerZonas;
use App\Application\Zona\RegistrarZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('lista todas las zonas registradas', function () {
    app(RegistrarZona::class)->execute(nombre: 'Zona 1', descripcion: null, estado: 'ACTIVO');
    app(RegistrarZona::class)->execute(nombre: 'Zona 2', descripcion: null, estado: 'INACTIVO');

    $zonas = app(ObtenerZonas::class)->execute();

    expect($zonas)->toHaveCount(2);
});

it('obtiene una zona por id', function () {
    $zona = app(RegistrarZona::class)->execute(nombre: 'Zona X', descripcion: null, estado: 'ACTIVO');

    $obtenida = app(ObtenerZona::class)->execute($zona->id());

    expect($obtenida)->not->toBeNull();
    expect($obtenida->nombre())->toBe('Zona X');
});
