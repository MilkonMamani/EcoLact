<?php

use App\Domain\Proveedor\Proveedor;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Infrastructure\Persistence\Repositories\EloquentProveedorRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearUsuarioProveedor(): User
{
    $role = RoleModel::create(['nombre' => 'PROVEEDOR', 'descripcion' => null]);

    return User::create([
        'role_id' => $role->id,
        'username' => 'proveedor.test',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
}

it('guarda un proveedor y lo mapea correctamente desde MySQL/SQLite a la entidad de dominio', function () {
    $repository = new EloquentProveedorRepository;

    $zona = ZonaModel::create(['nombre' => 'Zona Central', 'descripcion' => null, 'estado' => 'ACTIVO']);
    $user = crearUsuarioProveedor();

    $proveedor = new Proveedor(
        id: null,
        userId: $user->id,
        zonaId: $zona->id,
        dni: '87654321',
        nombres: 'Maria',
        apellidos: 'Condori',
        telefono: '71112222',
        qrToken: 'qr-token-0001',
        estado: EstadoUsuario::ACTIVO,
        fechaRegistro: new DateTimeImmutable('2026-01-10'),
    );

    $guardado = $repository->guardar($proveedor);

    expect($guardado->id())->not->toBeNull();
    expect($guardado->estado())->toBe(EstadoUsuario::ACTIVO);
    expect($guardado->fechaRegistro())->toBeInstanceOf(DateTimeImmutable::class);
    expect($guardado->fechaRegistro()->format('Y-m-d'))->toBe('2026-01-10');

    expect($repository->buscarPorId($guardado->id())?->dni())->toBe('87654321');
    expect($repository->buscarPorDni('87654321')?->id())->toBe($guardado->id());
    expect($repository->buscarPorUserId($user->id)?->id())->toBe($guardado->id());
    expect($repository->listarPorZona($zona->id))->toHaveCount(1);
});

it('actualiza un proveedor existente', function () {
    $repository = new EloquentProveedorRepository;

    $zona = ZonaModel::create(['nombre' => 'Zona A', 'descripcion' => null, 'estado' => 'ACTIVO']);
    $user = crearUsuarioProveedor();

    $guardado = $repository->guardar(new Proveedor(
        id: null,
        userId: $user->id,
        zonaId: $zona->id,
        dni: '11112222',
        nombres: 'Juan',
        apellidos: 'Perez',
        telefono: null,
        qrToken: 'qr-token-0002',
        estado: EstadoUsuario::ACTIVO,
        fechaRegistro: new DateTimeImmutable('2026-02-01'),
    ));

    $actualizado = $repository->actualizar(new Proveedor(
        id: $guardado->id(),
        userId: $user->id,
        zonaId: $zona->id,
        dni: '11112222',
        nombres: 'Juan',
        apellidos: 'Perez',
        telefono: '70000000',
        qrToken: 'qr-token-0002',
        estado: EstadoUsuario::INACTIVO,
        fechaRegistro: new DateTimeImmutable('2026-02-01'),
    ));

    expect($actualizado->telefono())->toBe('70000000');
    expect($actualizado->estado())->toBe(EstadoUsuario::INACTIVO);
});
