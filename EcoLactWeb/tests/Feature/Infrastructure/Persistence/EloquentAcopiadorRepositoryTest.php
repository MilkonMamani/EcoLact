<?php

use App\Domain\Acopiador\Acopiador;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Infrastructure\Persistence\Repositories\EloquentAcopiadorRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearUsuarioAcopiador(): User
{
    $role = RoleModel::create(['nombre' => 'ACOPIADOR', 'descripcion' => null]);

    return User::create([
        'role_id' => $role->id,
        'username' => 'acopiador.test',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
}

it('guarda y mapea un acopiador con fecha de ingreso nula', function () {
    $repository = new EloquentAcopiadorRepository;

    $zona = ZonaModel::create(['nombre' => 'Zona Este', 'descripcion' => null, 'estado' => 'ACTIVO']);
    $user = crearUsuarioAcopiador();

    $guardado = $repository->guardar(new Acopiador(
        id: null,
        userId: $user->id,
        zonaId: $zona->id,
        dni: '55556666',
        nombres: 'Pedro',
        apellidos: 'Mamani',
        telefono: null,
        estado: EstadoUsuario::ACTIVO,
        fechaIngreso: null,
    ));

    expect($guardado->id())->not->toBeNull();
    expect($guardado->fechaIngreso())->toBeNull();

    $encontrado = $repository->buscarPorId($guardado->id());
    expect($encontrado?->fechaIngreso())->toBeNull();
    expect($repository->buscarPorDni('55556666')?->id())->toBe($guardado->id());
    expect($repository->buscarPorUserId($user->id)?->id())->toBe($guardado->id());
    expect($repository->listarPorZona($zona->id))->toHaveCount(1);
});

it('guarda y mapea un acopiador con fecha de ingreso', function () {
    $repository = new EloquentAcopiadorRepository;

    $zona = ZonaModel::create(['nombre' => 'Zona Oeste', 'descripcion' => null, 'estado' => 'ACTIVO']);
    $user = crearUsuarioAcopiador();

    $guardado = $repository->guardar(new Acopiador(
        id: null,
        userId: $user->id,
        zonaId: $zona->id,
        dni: '77778888',
        nombres: 'Rosa',
        apellidos: 'Flores',
        telefono: '79998888',
        estado: EstadoUsuario::ACTIVO,
        fechaIngreso: new DateTimeImmutable('2026-03-05'),
    ));

    $encontrado = $repository->buscarPorId($guardado->id());
    expect($encontrado?->fechaIngreso()?->format('Y-m-d'))->toBe('2026-03-05');
});
