<?php

use App\Application\EncargadoPlanta\DesactivarEncargadoPlanta;
use App\Application\EncargadoPlanta\ReactivarEncargadoPlanta;
use App\Application\EncargadoPlanta\RegistrarEncargadoPlanta;
use App\Domain\Shared\Enums\EstadoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('reactiva un encargado de planta inactivo', function () {
    fixture_rol('ENCARGADO_PLANTA');

    $encargado = app(RegistrarEncargadoPlanta::class)->execute(
        dni: '40404040',
        nombres: 'Ruben',
        apellidos: 'Choquehuanca',
        telefono: null,
        username: 'ruben.choquehuanca',
        password: 'Secreto#123',
    );

    app(DesactivarEncargadoPlanta::class)->execute($encargado->id());

    $reactivado = app(ReactivarEncargadoPlanta::class)->execute($encargado->id());

    expect($reactivado->estado())->toBe(EstadoUsuario::ACTIVO);
    $this->assertDatabaseHas('encargados_planta', ['id' => $encargado->id(), 'estado' => 'ACTIVO']);
});

it('rechaza reactivar un encargado de planta que ya está activo', function () {
    fixture_rol('ENCARGADO_PLANTA');

    $encargado = app(RegistrarEncargadoPlanta::class)->execute(
        dni: '70707070',
        nombres: 'Nora',
        apellidos: 'Quenta',
        telefono: null,
        username: 'nora.quenta',
        password: 'Secreto#123',
    );

    app(ReactivarEncargadoPlanta::class)->execute($encargado->id());
})->throws(DomainException::class, 'El encargado de planta ya se encuentra activo.');

it('rechaza reactivar un encargado de planta que no existe', function () {
    app(ReactivarEncargadoPlanta::class)->execute(999999);
})->throws(DomainException::class, 'El encargado de planta indicado no existe.');
