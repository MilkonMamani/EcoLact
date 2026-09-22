<?php

use App\Application\Calidad\ActualizarReglaCalidad;
use App\Application\Calidad\RegistrarReglaCalidad;
use App\Application\Shared\CampoActualizable;
use App\Domain\Shared\Enums\ResultadoCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('actualiza una regla de calidad existente con los nuevos valores recibidos', function () {
    $parametro = fixture_parametro('PH');

    $regla = app(RegistrarReglaCalidad::class)->execute(
        parametroId: $parametro->id,
        valorMinimo: 6.2,
        valorMaximo: 6.8,
        resultadoIncumplimiento: ResultadoCalidad::OBSERVADA,
        prioridad: 1,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: null,
        estado: 'ACTIVO',
    );

    $actualizada = app(ActualizarReglaCalidad::class)->execute(
        reglaId: $regla->id(),
        valorMinimo: CampoActualizable::conValor(6.3),
        valorMaximo: CampoActualizable::conValor(6.9),
        estado: 'INACTIVO',
    );

    expect($actualizada->valorMinimo())->toBe(6.3);
    expect($actualizada->valorMaximo())->toBe(6.9);
    expect($actualizada->estado())->toBe('INACTIVO');
    // Los campos no enviados conservan su valor original.
    expect($actualizada->resultadoIncumplimiento())->toBe(ResultadoCalidad::OBSERVADA);
    expect($actualizada->parametroId())->toBe($parametro->id);
});

it('rechaza actualizar una regla de calidad que no existe', function () {
    app(ActualizarReglaCalidad::class)->execute(reglaId: 999999, estado: 'INACTIVO');
})->throws(DomainException::class, 'La regla de calidad indicada no existe.');
