<?php

use App\Application\Ruta\RegistrarRuta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra una ruta nueva dentro de una zona', function () {
    $zona = fixture_zona();

    $ruta = app(RegistrarRuta::class)->execute(
        zonaId: $zona->id,
        nombre: 'Ruta principal',
        descripcion: 'Recorrido de la mañana',
    );

    expect($ruta->id())->not->toBeNull();
    expect($ruta->zonaId())->toBe($zona->id);
    expect($ruta->estado())->toBe('ACTIVO');

    $this->assertDatabaseHas('rutas', ['zona_id' => $zona->id, 'nombre' => 'Ruta principal']);
});

it('rechaza dos rutas con el mismo nombre en la misma zona', function () {
    $zona = fixture_zona();
    app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta A');

    app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta A');
})->throws(DomainException::class, 'Ya existe una ruta llamada "Ruta A" en esta zona.');

it('permite el mismo nombre de ruta en zonas distintas', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');

    app(RegistrarRuta::class)->execute(zonaId: $zonaA->id, nombre: 'Ruta principal');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zonaB->id, nombre: 'Ruta principal');

    expect($ruta->id())->not->toBeNull();
});
