<?php

use App\Application\Proveedor\RegistrarProveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra un proveedor nuevo junto con su usuario de acceso y la zona inicial', function () {
    fixture_rol('PROVEEDOR');
    $zona = fixture_zona();

    $proveedor = app(RegistrarProveedor::class)->execute(
        dni: '12345678',
        nombres: 'Ana',
        apellidos: 'Quispe',
        telefono: '70000000',
        zonaId: $zona->id,
        username: 'ana.quispe',
        password: 'Secreto#123',
    );

    expect($proveedor->id())->not->toBeNull();
    expect($proveedor->dni())->toBe('12345678');
    expect($proveedor->zonaId())->toBe($zona->id);
    expect($proveedor->qrToken())->not->toBeEmpty();

    $this->assertDatabaseHas('users', ['username' => 'ana.quispe']);
    $this->assertDatabaseHas('proveedor_zona_historial', [
        'proveedor_id' => $proveedor->id(),
        'zona_id' => $zona->id,
        'fecha_fin' => null,
    ]);

    $usuario = User::where('username', 'ana.quispe')->first();
    expect($usuario->password)->not->toBe('Secreto#123');
});

it('rechaza el registro de un proveedor con DNI duplicado', function () {
    fixture_rol('PROVEEDOR');
    $zona = fixture_zona();

    app(RegistrarProveedor::class)->execute(
        dni: '99999999',
        nombres: 'Luis',
        apellidos: 'Mamani',
        telefono: null,
        zonaId: $zona->id,
        username: 'luis.mamani',
        password: 'Secreto#123',
    );

    app(RegistrarProveedor::class)->execute(
        dni: '99999999',
        nombres: 'Otro',
        apellidos: 'Proveedor',
        telefono: null,
        zonaId: $zona->id,
        username: 'otro.proveedor',
        password: 'Secreto#123',
    );
})->throws(DomainException::class, 'Ya existe un proveedor registrado con el DNI 99999999.');
