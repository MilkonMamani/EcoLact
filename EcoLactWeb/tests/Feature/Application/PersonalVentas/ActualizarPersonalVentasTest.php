<?php

use App\Application\PersonalVentas\ActualizarPersonalVentas;
use App\Application\PersonalVentas\RegistrarPersonalVentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('actualiza nombres, apellidos y teléfono sin afectar el dni ni el estado', function () {
    fixture_rol('VENTAS');

    $personal = app(RegistrarPersonalVentas::class)->execute(
        dni: '34343434',
        nombres: 'Hugo',
        apellidos: 'Apaza',
        telefono: '70000001',
        username: 'hugo.apaza',
        password: 'Secreto#123',
    );

    $actualizado = app(ActualizarPersonalVentas::class)->execute(
        personalId: $personal->id(),
        nombres: 'Hugo Renato',
        telefono: '70000002',
    );

    expect($actualizado->nombres())->toBe('Hugo Renato');
    expect($actualizado->apellidos())->toBe('Apaza');
    expect($actualizado->telefono())->toBe('70000002');
    expect($actualizado->estado())->toBe($personal->estado());
    expect($actualizado->dni())->toBe('34343434');
});

it('rechaza actualizar personal de ventas que no existe', function () {
    app(ActualizarPersonalVentas::class)->execute(personalId: 999999, nombres: 'X');
})->throws(DomainException::class, 'El personal de ventas indicado no existe.');

it('ActualizarPersonalVentas no admite cambiar el estado (eso es Desactivar/ReactivarPersonalVentas)', function () {
    $reflexion = new ReflectionMethod(ActualizarPersonalVentas::class, 'execute');
    $nombresParametros = array_map(
        fn (ReflectionParameter $parametro): string => $parametro->getName(),
        $reflexion->getParameters(),
    );

    expect($nombresParametros)->not->toContain('estado');
});
