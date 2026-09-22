<?php

use App\Application\PersonalVentas\DesactivarPersonalVentas;
use App\Application\PersonalVentas\ReactivarPersonalVentas;
use App\Application\PersonalVentas\RegistrarPersonalVentas;
use App\Domain\Shared\Enums\EstadoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('reactiva personal de ventas inactivo', function () {
    fixture_rol('VENTAS');

    $personal = app(RegistrarPersonalVentas::class)->execute(
        dni: '40404040',
        nombres: 'Ruben',
        apellidos: 'Choquehuanca',
        telefono: null,
        username: 'ruben.choquehuanca',
        password: 'Secreto#123',
    );

    app(DesactivarPersonalVentas::class)->execute($personal->id());

    $reactivado = app(ReactivarPersonalVentas::class)->execute($personal->id());

    expect($reactivado->estado())->toBe(EstadoUsuario::ACTIVO);
    $this->assertDatabaseHas('personal_ventas', ['id' => $personal->id(), 'estado' => 'ACTIVO']);
});

it('rechaza reactivar personal de ventas que ya está activo', function () {
    fixture_rol('VENTAS');

    $personal = app(RegistrarPersonalVentas::class)->execute(
        dni: '70707070',
        nombres: 'Nora',
        apellidos: 'Quenta',
        telefono: null,
        username: 'nora.quenta',
        password: 'Secreto#123',
    );

    app(ReactivarPersonalVentas::class)->execute($personal->id());
})->throws(DomainException::class, 'El personal de ventas ya se encuentra activo.');

it('rechaza reactivar personal de ventas que no existe', function () {
    app(ReactivarPersonalVentas::class)->execute(999999);
})->throws(DomainException::class, 'El personal de ventas indicado no existe.');
