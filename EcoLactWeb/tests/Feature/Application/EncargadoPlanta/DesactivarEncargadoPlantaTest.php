<?php

use App\Application\EncargadoPlanta\DesactivarEncargadoPlanta;
use App\Application\EncargadoPlanta\RegistrarEncargadoPlanta;
use App\Domain\Shared\Enums\EstadoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('desactiva un encargado de planta ACTIVO y lo deja INACTIVO', function () {
    fixture_rol('ENCARGADO_PLANTA');

    $encargado = app(RegistrarEncargadoPlanta::class)->execute(
        dni: '20202020',
        nombres: 'Teodoro',
        apellidos: 'Mamani',
        telefono: null,
        username: 'teodoro.mamani',
        password: 'Secreto#123',
    );

    $desactivado = app(DesactivarEncargadoPlanta::class)->execute($encargado->id());

    expect($desactivado->estado())->toBe(EstadoUsuario::INACTIVO);

    // No se elimina nada: el encargado y su usuario persisten.
    $this->assertDatabaseHas('encargados_planta', ['id' => $encargado->id()]);
    $this->assertDatabaseHas('users', ['id' => $encargado->userId()]);
});

it('rechaza desactivar un encargado de planta que ya está inactivo', function () {
    fixture_rol('ENCARGADO_PLANTA');

    $encargado = app(RegistrarEncargadoPlanta::class)->execute(
        dni: '30303030',
        nombres: 'Beatriz',
        apellidos: 'Colque',
        telefono: null,
        username: 'beatriz.colque',
        password: 'Secreto#123',
    );

    app(DesactivarEncargadoPlanta::class)->execute($encargado->id());
    app(DesactivarEncargadoPlanta::class)->execute($encargado->id());
})->throws(DomainException::class, 'El encargado de planta ya se encuentra inactivo.');

it('rechaza desactivar un encargado de planta que no existe', function () {
    app(DesactivarEncargadoPlanta::class)->execute(999999);
})->throws(DomainException::class, 'El encargado de planta indicado no existe.');
