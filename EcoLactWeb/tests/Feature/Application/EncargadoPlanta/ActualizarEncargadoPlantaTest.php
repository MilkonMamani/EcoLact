<?php

use App\Application\EncargadoPlanta\ActualizarEncargadoPlanta;
use App\Application\EncargadoPlanta\RegistrarEncargadoPlanta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('actualiza nombres, apellidos y teléfono sin afectar el dni ni el estado', function () {
    fixture_rol('ENCARGADO_PLANTA');

    $encargado = app(RegistrarEncargadoPlanta::class)->execute(
        dni: '34343434',
        nombres: 'Hugo',
        apellidos: 'Apaza',
        telefono: '70000001',
        username: 'hugo.apaza',
        password: 'Secreto#123',
    );

    $actualizado = app(ActualizarEncargadoPlanta::class)->execute(
        encargadoId: $encargado->id(),
        nombres: 'Hugo Renato',
        telefono: '70000002',
    );

    expect($actualizado->nombres())->toBe('Hugo Renato');
    expect($actualizado->apellidos())->toBe('Apaza');
    expect($actualizado->telefono())->toBe('70000002');
    expect($actualizado->estado())->toBe($encargado->estado());
    expect($actualizado->dni())->toBe('34343434');
});

it('rechaza actualizar un encargado de planta que no existe', function () {
    app(ActualizarEncargadoPlanta::class)->execute(encargadoId: 999999, nombres: 'X');
})->throws(DomainException::class, 'El encargado de planta indicado no existe.');

it('ActualizarEncargadoPlanta no admite cambiar el estado (eso es Desactivar/ReactivarEncargadoPlanta)', function () {
    $reflexion = new ReflectionMethod(ActualizarEncargadoPlanta::class, 'execute');
    $nombresParametros = array_map(
        fn (ReflectionParameter $parametro): string => $parametro->getName(),
        $reflexion->getParameters(),
    );

    expect($nombresParametros)->not->toContain('estado');
});
