<?php

use App\Application\AnalistaCalidad\ActualizarAnalistaCalidad;
use App\Application\AnalistaCalidad\RegistrarAnalistaCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('actualiza nombres, apellidos y teléfono sin afectar el dni ni el estado', function () {
    fixture_rol('CALIDAD');

    $analista = app(RegistrarAnalistaCalidad::class)->execute(
        dni: '34343434',
        nombres: 'Hugo',
        apellidos: 'Apaza',
        telefono: '70000001',
        username: 'hugo.apaza',
        password: 'Secreto#123',
    );

    $actualizado = app(ActualizarAnalistaCalidad::class)->execute(
        analistaId: $analista->id(),
        nombres: 'Hugo Renato',
        telefono: '70000002',
    );

    expect($actualizado->nombres())->toBe('Hugo Renato');
    expect($actualizado->apellidos())->toBe('Apaza');
    expect($actualizado->telefono())->toBe('70000002');
    expect($actualizado->estado())->toBe($analista->estado());
    expect($actualizado->dni())->toBe('34343434');
});

it('rechaza actualizar un analista de calidad que no existe', function () {
    app(ActualizarAnalistaCalidad::class)->execute(analistaId: 999999, nombres: 'X');
})->throws(DomainException::class, 'El analista de calidad indicado no existe.');

it('ActualizarAnalistaCalidad no admite cambiar el estado (eso es Desactivar/ReactivarAnalistaCalidad)', function () {
    $reflexion = new ReflectionMethod(ActualizarAnalistaCalidad::class, 'execute');
    $nombresParametros = array_map(
        fn (ReflectionParameter $parametro): string => $parametro->getName(),
        $reflexion->getParameters(),
    );

    expect($nombresParametros)->not->toContain('estado');
});
