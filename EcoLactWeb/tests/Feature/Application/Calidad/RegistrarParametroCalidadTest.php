<?php

use App\Application\Calidad\RegistrarParametroCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra un nuevo parámetro de calidad', function () {
    $parametro = app(RegistrarParametroCalidad::class)->execute(
        codigo: 'UREA',
        nombre: 'Urea',
        unidad: 'mg/dL',
        estado: 'ACTIVO',
    );

    expect($parametro->id())->not->toBeNull();
    expect($parametro->codigo())->toBe('UREA');
    expect($parametro->nombre())->toBe('Urea');
    expect($parametro->unidad())->toBe('mg/dL');
});

it('rechaza registrar un parámetro con un código duplicado', function () {
    app(RegistrarParametroCalidad::class)->execute(
        codigo: 'GRASA',
        nombre: 'Grasa',
        unidad: '%',
        estado: 'ACTIVO',
    );

    app(RegistrarParametroCalidad::class)->execute(
        codigo: 'GRASA',
        nombre: 'Grasa (otro nombre)',
        unidad: '%',
        estado: 'ACTIVO',
    );
})->throws(DomainException::class, 'Ya existe un parámetro de calidad con el código GRASA.');
