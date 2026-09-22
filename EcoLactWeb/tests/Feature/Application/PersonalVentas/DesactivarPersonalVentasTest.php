<?php

use App\Application\PersonalVentas\DesactivarPersonalVentas;
use App\Application\PersonalVentas\RegistrarPersonalVentas;
use App\Domain\Shared\Enums\EstadoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('desactiva un miembro de personal de ventas ACTIVO y lo deja INACTIVO', function () {
    fixture_rol('VENTAS');

    $personal = app(RegistrarPersonalVentas::class)->execute(
        dni: '20202020',
        nombres: 'Teodoro',
        apellidos: 'Mamani',
        telefono: null,
        username: 'teodoro.mamani',
        password: 'Secreto#123',
    );

    $desactivado = app(DesactivarPersonalVentas::class)->execute($personal->id());

    expect($desactivado->estado())->toBe(EstadoUsuario::INACTIVO);

    $this->assertDatabaseHas('personal_ventas', ['id' => $personal->id()]);
    $this->assertDatabaseHas('users', ['id' => $personal->userId()]);
});

it('rechaza desactivar personal de ventas que ya está inactivo', function () {
    fixture_rol('VENTAS');

    $personal = app(RegistrarPersonalVentas::class)->execute(
        dni: '30303030',
        nombres: 'Beatriz',
        apellidos: 'Colque',
        telefono: null,
        username: 'beatriz.colque',
        password: 'Secreto#123',
    );

    app(DesactivarPersonalVentas::class)->execute($personal->id());
    app(DesactivarPersonalVentas::class)->execute($personal->id());
})->throws(DomainException::class, 'El personal de ventas ya se encuentra inactivo.');

it('rechaza desactivar personal de ventas que no existe', function () {
    app(DesactivarPersonalVentas::class)->execute(999999);
})->throws(DomainException::class, 'El personal de ventas indicado no existe.');
