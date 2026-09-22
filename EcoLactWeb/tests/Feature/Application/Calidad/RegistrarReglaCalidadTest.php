<?php

use App\Application\Calidad\RegistrarReglaCalidad;
use App\Domain\Shared\Enums\ResultadoCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra una regla de calidad con los valores exactos que envía el administrador', function () {
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

    expect($regla->id())->not->toBeNull();
    expect($regla->parametroId())->toBe($parametro->id);
    expect($regla->valorMinimo())->toBe(6.2);
    expect($regla->valorMaximo())->toBe(6.8);
    expect($regla->resultadoIncumplimiento())->toBe(ResultadoCalidad::OBSERVADA);
});

it('no inventa ni sustituye los valores de calidad recibidos por otros por defecto', function () {
    $parametro = fixture_parametro('DENSIDAD');

    // Valores deliberadamente distintos de cualquier "estándar" conocido,
    // para comprobar que el Use Case no los reemplaza por defaults propios.
    $regla = app(RegistrarReglaCalidad::class)->execute(
        parametroId: $parametro->id,
        valorMinimo: 1.011,
        valorMaximo: 1.033,
        resultadoIncumplimiento: ResultadoCalidad::RECHAZADA,
        prioridad: 5,
        fechaInicio: new DateTimeImmutable('2026-03-01'),
        fechaFin: new DateTimeImmutable('2026-12-31'),
        estado: 'ACTIVO',
    );

    expect($regla->valorMinimo())->toBe(1.011);
    expect($regla->valorMaximo())->toBe(1.033);
    expect($regla->prioridad())->toBe(5);
    expect($regla->fechaFin()?->format('Y-m-d'))->toBe('2026-12-31');
});

it('rechaza registrar una regla para un parámetro que no existe', function () {
    app(RegistrarReglaCalidad::class)->execute(
        parametroId: 999999,
        valorMinimo: 1.0,
        valorMaximo: 2.0,
        resultadoIncumplimiento: ResultadoCalidad::OBSERVADA,
        prioridad: 1,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: null,
        estado: 'ACTIVO',
    );
})->throws(DomainException::class, 'El parámetro de calidad indicado no existe.');

it('rechaza una regla cuya fecha fin es anterior a la fecha inicio', function () {
    $parametro = fixture_parametro('GRASA');

    app(RegistrarReglaCalidad::class)->execute(
        parametroId: $parametro->id,
        valorMinimo: 3.0,
        valorMaximo: 6.0,
        resultadoIncumplimiento: ResultadoCalidad::OBSERVADA,
        prioridad: 1,
        fechaInicio: new DateTimeImmutable('2026-06-01'),
        fechaFin: new DateTimeImmutable('2026-01-01'),
        estado: 'ACTIVO',
    );
})->throws(DomainException::class, 'La fecha fin de la regla no puede ser anterior a la fecha inicio.');
