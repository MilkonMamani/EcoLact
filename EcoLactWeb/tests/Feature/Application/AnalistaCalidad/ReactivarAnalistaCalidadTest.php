<?php

use App\Application\AnalistaCalidad\DesactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\ReactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\RegistrarAnalistaCalidad;
use App\Domain\Shared\Enums\EstadoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('reactiva un analista de calidad inactivo', function () {
    fixture_rol('CALIDAD');

    $analista = app(RegistrarAnalistaCalidad::class)->execute(
        dni: '40404040',
        nombres: 'Ruben',
        apellidos: 'Choquehuanca',
        telefono: null,
        username: 'ruben.choquehuanca',
        password: 'Secreto#123',
    );

    app(DesactivarAnalistaCalidad::class)->execute($analista->id());

    $reactivado = app(ReactivarAnalistaCalidad::class)->execute($analista->id());

    expect($reactivado->estado())->toBe(EstadoUsuario::ACTIVO);
    $this->assertDatabaseHas('analistas_calidad', ['id' => $analista->id(), 'estado' => 'ACTIVO']);
});

it('rechaza reactivar un analista de calidad que ya está activo', function () {
    fixture_rol('CALIDAD');

    $analista = app(RegistrarAnalistaCalidad::class)->execute(
        dni: '70707070',
        nombres: 'Nora',
        apellidos: 'Quenta',
        telefono: null,
        username: 'nora.quenta',
        password: 'Secreto#123',
    );

    app(ReactivarAnalistaCalidad::class)->execute($analista->id());
})->throws(DomainException::class, 'El analista de calidad ya se encuentra activo.');

it('rechaza reactivar un analista de calidad que no existe', function () {
    app(ReactivarAnalistaCalidad::class)->execute(999999);
})->throws(DomainException::class, 'El analista de calidad indicado no existe.');
