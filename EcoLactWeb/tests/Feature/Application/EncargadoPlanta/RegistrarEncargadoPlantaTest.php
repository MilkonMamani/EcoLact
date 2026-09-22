<?php

use App\Application\EncargadoPlanta\RegistrarEncargadoPlanta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra un encargado de planta nuevo junto con su usuario de acceso', function () {
    fixture_rol('ENCARGADO_PLANTA');

    $encargado = app(RegistrarEncargadoPlanta::class)->execute(
        dni: '12345678',
        nombres: 'Marco',
        apellidos: 'Flores',
        telefono: '70000000',
        username: 'marco.flores',
        password: 'Secreto#123',
    );

    expect($encargado->id())->not->toBeNull();
    expect($encargado->dni())->toBe('12345678');

    $this->assertDatabaseHas('users', ['username' => 'marco.flores']);
    $this->assertDatabaseHas('encargados_planta', ['dni' => '12345678']);
});

it('rechaza el registro de un encargado de planta con DNI duplicado', function () {
    fixture_rol('ENCARGADO_PLANTA');

    app(RegistrarEncargadoPlanta::class)->execute(
        dni: '99999999',
        nombres: 'Luis',
        apellidos: 'Mamani',
        telefono: null,
        username: 'luis.mamani',
        password: 'Secreto#123',
    );

    app(RegistrarEncargadoPlanta::class)->execute(
        dni: '99999999',
        nombres: 'Otro',
        apellidos: 'Encargado',
        telefono: null,
        username: 'otro.encargado',
        password: 'Secreto#123',
    );
})->throws(DomainException::class, 'Ya existe un encargado de planta registrado con el DNI 99999999.');

it('rechaza el registro de un encargado de planta con username duplicado', function () {
    fixture_rol('ENCARGADO_PLANTA');

    app(RegistrarEncargadoPlanta::class)->execute(
        dni: '11112222',
        nombres: 'Pedro',
        apellidos: 'Rojas',
        telefono: null,
        username: 'pedro.rojas',
        password: 'Secreto#123',
    );

    app(RegistrarEncargadoPlanta::class)->execute(
        dni: '33334444',
        nombres: 'Otro',
        apellidos: 'Encargado',
        telefono: null,
        username: 'pedro.rojas',
        password: 'Secreto#123',
    );
})->throws(DomainException::class, 'Ya existe un usuario registrado con el username pedro.rojas.');

it('guarda el password del encargado de planta hasheado, nunca en texto plano', function () {
    fixture_rol('ENCARGADO_PLANTA');

    app(RegistrarEncargadoPlanta::class)->execute(
        dni: '77778888',
        nombres: 'Rosa',
        apellidos: 'Choque',
        telefono: null,
        username: 'rosa.choque',
        password: 'Secreto#123',
    );

    $usuario = User::where('username', 'rosa.choque')->first();

    expect($usuario->password)->not->toBe('Secreto#123');
    expect(Hash::check('Secreto#123', $usuario->password))->toBeTrue();
});
