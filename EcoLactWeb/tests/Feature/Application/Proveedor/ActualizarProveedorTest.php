<?php

use App\Application\Proveedor\ActualizarProveedor;
use App\Application\Proveedor\RegistrarProveedor;
use App\Domain\Proveedor\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function registrarProveedorParaActualizar(string $dni, ?int $zonaId = null): Proveedor
{
    fixture_rol('PROVEEDOR');
    $zona = $zonaId !== null ? null : fixture_zona();

    return app(RegistrarProveedor::class)->execute(
        dni: $dni,
        nombres: 'Original',
        apellidos: 'Apellido',
        telefono: '70000000',
        zonaId: $zonaId ?? $zona->id,
        username: 'usuario.'.$dni,
        password: 'Secreto#123',
    );
}

it('actualiza el DNI de un proveedor correctamente', function () {
    $proveedor = registrarProveedorParaActualizar('11112222');

    $actualizado = app(ActualizarProveedor::class)->execute(
        proveedorId: $proveedor->id(),
        dni: '99998888',
    );

    expect($actualizado->dni())->toBe('99998888');
});

it('no permite actualizar a un DNI que ya pertenece a otro proveedor', function () {
    $proveedorA = registrarProveedorParaActualizar('11111111');
    $proveedorB = registrarProveedorParaActualizar('22222222');

    app(ActualizarProveedor::class)->execute(
        proveedorId: $proveedorB->id(),
        dni: $proveedorA->dni(),
    );
})->throws(DomainException::class, 'Ya existe otro proveedor registrado con el DNI 11111111.');

it('permite conservar el mismo DNI propio del proveedor sin rechazarlo', function () {
    $proveedor = registrarProveedorParaActualizar('33333333');

    $actualizado = app(ActualizarProveedor::class)->execute(
        proveedorId: $proveedor->id(),
        dni: '33333333',
        nombres: 'Nombre Actualizado',
    );

    expect($actualizado->dni())->toBe('33333333');
    expect($actualizado->nombres())->toBe('Nombre Actualizado');
});

it('al cambiar de zona, conserva el historial cerrando el vigente y abriendo uno nuevo', function () {
    fixture_rol('PROVEEDOR');
    $zonaOrigen = fixture_zona('Zona Origen Proveedor');
    $zonaDestino = fixture_zona('Zona Destino Proveedor');

    $proveedor = app(RegistrarProveedor::class)->execute(
        dni: '44444444',
        nombres: 'Mario',
        apellidos: 'Suarez',
        telefono: null,
        zonaId: $zonaOrigen->id,
        username: 'mario.suarez',
        password: 'Secreto#123',
    );

    $actualizado = app(ActualizarProveedor::class)->execute(
        proveedorId: $proveedor->id(),
        nuevaZonaId: $zonaDestino->id,
        motivoCambioZona: 'Reubicación',
    );

    expect($actualizado->zonaId())->toBe($zonaDestino->id);

    $this->assertDatabaseHas('proveedor_zona_historial', [
        'proveedor_id' => $proveedor->id(),
        'zona_id' => $zonaOrigen->id,
    ]);
    $this->assertDatabaseMissing('proveedor_zona_historial', [
        'proveedor_id' => $proveedor->id(),
        'zona_id' => $zonaOrigen->id,
        'fecha_fin' => null,
    ]);
    $this->assertDatabaseHas('proveedor_zona_historial', [
        'proveedor_id' => $proveedor->id(),
        'zona_id' => $zonaDestino->id,
        'fecha_fin' => null,
    ]);
    $this->assertDatabaseCount('proveedor_zona_historial', 2);
});

it('ActualizarProveedor no admite ni modifica el username del proveedor', function () {
    $reflexion = new ReflectionMethod(ActualizarProveedor::class, 'execute');
    $nombresParametros = array_map(
        fn (ReflectionParameter $parametro): string => $parametro->getName(),
        $reflexion->getParameters(),
    );

    expect($nombresParametros)->not->toContain('username');

    // Aunque se intente enviar un username junto al resto de datos, el
    // proveedor conserva el username original: el Use Case no lo procesa.
    $proveedor = registrarProveedorParaActualizar('55555555');
    $usernameOriginal = User::find($proveedor->userId())->username;

    app(ActualizarProveedor::class)->execute(
        proveedorId: $proveedor->id(),
        nombres: 'Cambio Nombres',
    );

    $usernameDespues = User::find($proveedor->userId())->username;
    expect($usernameDespues)->toBe($usernameOriginal);
});
