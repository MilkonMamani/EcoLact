<?php

use App\Application\Calidad\ObtenerParametroCalidad;
use App\Application\Calidad\ObtenerParametrosCalidad;
use App\Application\Calidad\RegistrarParametroCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('lista todos los parámetros de calidad, incluyendo los inactivos', function () {
    app(RegistrarParametroCalidad::class)->execute('GRASA', 'Grasa', '%', 'ACTIVO');
    app(RegistrarParametroCalidad::class)->execute('OBSOLETO', 'Parámetro obsoleto', null, 'INACTIVO');

    $parametros = app(ObtenerParametrosCalidad::class)->execute();

    expect($parametros)->toHaveCount(2);
});

it('obtiene un parámetro de calidad por id', function () {
    $parametro = app(RegistrarParametroCalidad::class)->execute('DENSIDAD', 'Densidad', 'g/mL', 'ACTIVO');

    $obtenido = app(ObtenerParametroCalidad::class)->execute($parametro->id());

    expect($obtenido)->not->toBeNull();
    expect($obtenido->codigo())->toBe('DENSIDAD');
});

it('devuelve null al buscar un parámetro de calidad que no existe', function () {
    $obtenido = app(ObtenerParametroCalidad::class)->execute(999999);

    expect($obtenido)->toBeNull();
});
