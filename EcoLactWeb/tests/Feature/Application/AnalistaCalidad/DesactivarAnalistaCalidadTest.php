<?php

use App\Application\AnalistaCalidad\DesactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\RegistrarAnalistaCalidad;
use App\Domain\Shared\Enums\EstadoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('desactiva un analista de calidad ACTIVO y lo deja INACTIVO', function () {
    fixture_rol('CALIDAD');

    $analista = app(RegistrarAnalistaCalidad::class)->execute(
        dni: '20202020',
        nombres: 'Teodoro',
        apellidos: 'Mamani',
        telefono: null,
        username: 'teodoro.mamani',
        password: 'Secreto#123',
    );

    $desactivado = app(DesactivarAnalistaCalidad::class)->execute($analista->id());

    expect($desactivado->estado())->toBe(EstadoUsuario::INACTIVO);

    $this->assertDatabaseHas('analistas_calidad', ['id' => $analista->id()]);
    $this->assertDatabaseHas('users', ['id' => $analista->userId()]);
});

it('rechaza desactivar un analista de calidad que ya está inactivo', function () {
    fixture_rol('CALIDAD');

    $analista = app(RegistrarAnalistaCalidad::class)->execute(
        dni: '30303030',
        nombres: 'Beatriz',
        apellidos: 'Colque',
        telefono: null,
        username: 'beatriz.colque',
        password: 'Secreto#123',
    );

    app(DesactivarAnalistaCalidad::class)->execute($analista->id());
    app(DesactivarAnalistaCalidad::class)->execute($analista->id());
})->throws(DomainException::class, 'El analista de calidad ya se encuentra inactivo.');

it('rechaza desactivar un analista de calidad que no existe', function () {
    app(DesactivarAnalistaCalidad::class)->execute(999999);
})->throws(DomainException::class, 'El analista de calidad indicado no existe.');
