<?php

use App\Application\Calidad\ActualizarParametroCalidad;
use App\Application\Calidad\RegistrarParametroCalidad;
use App\Application\Shared\CampoActualizable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('actualiza el nombre y la unidad de un parámetro de calidad correctamente', function () {
    $parametro = app(RegistrarParametroCalidad::class)->execute(
        codigo: 'GRASA',
        nombre: 'Grasa',
        unidad: '%',
        estado: 'ACTIVO',
    );

    $actualizado = app(ActualizarParametroCalidad::class)->execute(
        parametroId: $parametro->id(),
        nombre: 'Grasa Butírica',
        unidad: CampoActualizable::conValor('g/100mL'),
    );

    expect($actualizado->nombre())->toBe('Grasa Butírica');
    expect($actualizado->unidad())->toBe('g/100mL');
    expect($actualizado->codigo())->toBe('GRASA');
});

it('no permite cambiar el código de un parámetro de calidad', function () {
    $reflexion = new ReflectionMethod(ActualizarParametroCalidad::class, 'execute');
    $nombresParametros = array_map(
        fn (ReflectionParameter $parametro): string => $parametro->getName(),
        $reflexion->getParameters(),
    );

    expect($nombresParametros)->not->toContain('codigo');

    // Aunque cambien otros campos, el código técnico permanece intacto.
    $parametro = app(RegistrarParametroCalidad::class)->execute(
        codigo: 'PROTEINA',
        nombre: 'Proteína',
        unidad: '%',
        estado: 'ACTIVO',
    );

    $actualizado = app(ActualizarParametroCalidad::class)->execute(
        parametroId: $parametro->id(),
        nombre: 'Proteínas Totales',
    );

    expect($actualizado->codigo())->toBe('PROTEINA');
});

it('desactiva un parámetro de calidad sin reglas activas, sin eliminar su historial', function () {
    $parametro = app(RegistrarParametroCalidad::class)->execute(
        codigo: 'LACTOSA',
        nombre: 'Lactosa',
        unidad: '%',
        estado: 'ACTIVO',
    );

    $desactivado = app(ActualizarParametroCalidad::class)->execute(
        parametroId: $parametro->id(),
        estado: 'INACTIVO',
    );

    expect($desactivado->estado())->toBe('INACTIVO');
    $this->assertDatabaseHas('parametros_calidad', ['id' => $parametro->id(), 'codigo' => 'LACTOSA']);
});

it('no permite desactivar un parámetro que tiene una regla de calidad activa asociada', function () {
    $parametro = app(RegistrarParametroCalidad::class)->execute(
        codigo: 'PH',
        nombre: 'pH',
        unidad: 'pH',
        estado: 'ACTIVO',
    );

    fixture_regla_calidad($parametro->id(), 6.2, 6.8, 'OBSERVADA');

    app(ActualizarParametroCalidad::class)->execute(
        parametroId: $parametro->id(),
        estado: 'INACTIVO',
    );
})->throws(
    DomainException::class,
    'No se puede desactivar el parámetro: tiene reglas de calidad activas asociadas. Desactívelas primero.'
);

it('rechaza actualizar un parámetro de calidad que no existe', function () {
    app(ActualizarParametroCalidad::class)->execute(parametroId: 999999, nombre: 'X');
})->throws(DomainException::class, 'El parámetro de calidad indicado no existe.');
