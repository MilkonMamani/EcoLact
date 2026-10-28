<?php

use App\Application\Zona\RegistrarZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra una zona nueva', function () {
    $zona = app(RegistrarZona::class)->execute(
        nombre: 'Zona Nueva',
        descripcion: 'Sector recién incorporado',
        estado: 'ACTIVO',
    );

    expect($zona->id())->not->toBeNull();
    expect($zona->nombre())->toBe('Zona Nueva');
    expect($zona->descripcion())->toBe('Sector recién incorporado');
});

it('rechaza registrar una zona con un nombre duplicado', function () {
    app(RegistrarZona::class)->execute(nombre: 'Faón', descripcion: null, estado: 'ACTIVO');
    app(RegistrarZona::class)->execute(nombre: 'Faón', descripcion: null, estado: 'ACTIVO');
})->throws(DomainException::class, 'Ya existe una zona equivalente con el nombre Faón.');
