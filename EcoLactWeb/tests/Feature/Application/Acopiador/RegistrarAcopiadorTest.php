<?php

use App\Application\Acopiador\RegistrarAcopiador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra un acopiador nuevo junto con su usuario de acceso y la zona inicial', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '12345678',
        nombres: 'Marco',
        apellidos: 'Flores',
        telefono: '70000000',
        zonaId: $zona->id,
        username: 'marco.flores',
        password: 'Secreto#123',
    );

    expect($acopiador->id())->not->toBeNull();
    expect($acopiador->dni())->toBe('12345678');
    expect($acopiador->zonaId())->toBe($zona->id);

    $this->assertDatabaseHas('users', ['username' => 'marco.flores']);
    $this->assertDatabaseHas('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'zona_id' => $zona->id,
        'fecha_fin' => null,
    ]);
});

it('rechaza el registro de un acopiador con DNI duplicado', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    app(RegistrarAcopiador::class)->execute(
        dni: '99999999',
        nombres: 'Luis',
        apellidos: 'Mamani',
        telefono: null,
        zonaId: $zona->id,
        username: 'luis.mamani',
        password: 'Secreto#123',
    );

    app(RegistrarAcopiador::class)->execute(
        dni: '99999999',
        nombres: 'Otro',
        apellidos: 'Acopiador',
        telefono: null,
        zonaId: $zona->id,
        username: 'otro.acopiador',
        password: 'Secreto#123',
    );
})->throws(DomainException::class, 'Ya existe un acopiador registrado con el DNI 99999999.');

it('rechaza el registro de un acopiador con username duplicado', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    app(RegistrarAcopiador::class)->execute(
        dni: '11112222',
        nombres: 'Pedro',
        apellidos: 'Rojas',
        telefono: null,
        zonaId: $zona->id,
        username: 'pedro.rojas',
        password: 'Secreto#123',
    );

    app(RegistrarAcopiador::class)->execute(
        dni: '33334444',
        nombres: 'Otro',
        apellidos: 'Acopiador',
        telefono: null,
        zonaId: $zona->id,
        username: 'pedro.rojas',
        password: 'Secreto#123',
    );
})->throws(DomainException::class, 'Ya existe un usuario registrado con el username pedro.rojas.');

it('rechaza el registro de un acopiador en una zona que no existe', function () {
    fixture_rol('ACOPIADOR');

    app(RegistrarAcopiador::class)->execute(
        dni: '55556666',
        nombres: 'Elena',
        apellidos: 'Vargas',
        telefono: null,
        zonaId: 999999,
        username: 'elena.vargas',
        password: 'Secreto#123',
    );
})->throws(DomainException::class, 'La zona indicada no existe o no está activa.');

it('rechaza el registro de un acopiador en una zona inactiva', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();
    $zona->update(['estado' => 'INACTIVO']);

    app(RegistrarAcopiador::class)->execute(
        dni: '55556666',
        nombres: 'Elena',
        apellidos: 'Vargas',
        telefono: null,
        zonaId: $zona->id,
        username: 'elena.vargas',
        password: 'Secreto#123',
    );
})->throws(DomainException::class, 'La zona indicada no existe o no está activa.');

it('guarda el password del acopiador hasheado, nunca en texto plano', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    app(RegistrarAcopiador::class)->execute(
        dni: '77778888',
        nombres: 'Rosa',
        apellidos: 'Choque',
        telefono: null,
        zonaId: $zona->id,
        username: 'rosa.choque',
        password: 'Secreto#123',
    );

    $usuario = User::where('username', 'rosa.choque')->first();

    expect($usuario->password)->not->toBe('Secreto#123');
    expect(Hash::check('Secreto#123', $usuario->password))->toBeTrue();
});
