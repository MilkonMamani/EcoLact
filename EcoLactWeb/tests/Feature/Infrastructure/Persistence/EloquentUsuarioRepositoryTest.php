<?php

use App\Domain\Auth\Usuario;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Repositories\EloquentUsuarioRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('guarda un usuario y lo mapea correctamente a la entidad de dominio', function () {
    $repository = new EloquentUsuarioRepository;
    $role = RoleModel::create(['nombre' => 'ADMIN', 'descripcion' => null]);

    $usuario = new Usuario(
        id: null,
        roleId: $role->id,
        username: 'admin.principal',
        password: 'hash-generado',
        estado: EstadoUsuario::ACTIVO,
        debeCambiarPassword: true,
        ultimoAcceso: null,
    );

    $guardado = $repository->guardar($usuario);

    expect($guardado->id())->not->toBeNull();
    expect($guardado->username())->toBe('admin.principal');
    expect($guardado->estado())->toBe(EstadoUsuario::ACTIVO);
    expect($guardado->debeCambiarPassword())->toBeTrue();
    expect($guardado->ultimoAcceso())->toBeNull();

    expect($repository->buscarPorId($guardado->id())?->username())->toBe('admin.principal');
    expect($repository->buscarPorUsername('admin.principal')?->id())->toBe($guardado->id());
});

it('actualiza un usuario existente', function () {
    $repository = new EloquentUsuarioRepository;
    $role = RoleModel::create(['nombre' => 'OPERADOR', 'descripcion' => null]);

    $guardado = $repository->guardar(new Usuario(
        id: null,
        roleId: $role->id,
        username: 'operador.uno',
        password: 'hash-inicial',
        estado: EstadoUsuario::ACTIVO,
        debeCambiarPassword: true,
    ));

    $actualizado = $repository->actualizar(new Usuario(
        id: $guardado->id(),
        roleId: $role->id,
        username: 'operador.uno',
        password: 'hash-inicial',
        estado: EstadoUsuario::INACTIVO,
        debeCambiarPassword: false,
        ultimoAcceso: new DateTimeImmutable('2026-09-10 08:00:00'),
    ));

    expect($actualizado->estado())->toBe(EstadoUsuario::INACTIVO);
    expect($actualizado->debeCambiarPassword())->toBeFalse();
    expect($actualizado->ultimoAcceso()?->format('Y-m-d H:i:s'))->toBe('2026-09-10 08:00:00');
});

it('no encuentra un usuario inexistente', function () {
    $repository = new EloquentUsuarioRepository;

    expect($repository->buscarPorId(999))->toBeNull();
    expect($repository->buscarPorUsername('no-existe'))->toBeNull();
});
