<?php

use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Repositories\EloquentEncargadoPlantaRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearUsuarioEncargadoPlanta(): User
{
    $role = RoleModel::create(['nombre' => 'ENCARGADO_PLANTA', 'descripcion' => null]);

    return User::create([
        'role_id' => $role->id,
        'username' => 'encargado.test',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
}

it('guarda y mapea un encargado de planta con fecha de ingreso nula', function () {
    $repository = new EloquentEncargadoPlantaRepository;

    $user = crearUsuarioEncargadoPlanta();

    $guardado = $repository->guardar(new EncargadoPlanta(
        id: null,
        userId: $user->id,
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
    expect($repository->listar())->toHaveCount(1);
});

it('guarda y mapea un encargado de planta con fecha de ingreso', function () {
    $repository = new EloquentEncargadoPlantaRepository;

    $user = crearUsuarioEncargadoPlanta();

    $guardado = $repository->guardar(new EncargadoPlanta(
        id: null,
        userId: $user->id,
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

it('actualiza un encargado de planta existente', function () {
    $repository = new EloquentEncargadoPlantaRepository;

    $user = crearUsuarioEncargadoPlanta();

    $guardado = $repository->guardar(new EncargadoPlanta(
        id: null,
        userId: $user->id,
        dni: '11223344',
        nombres: 'Ana',
        apellidos: 'Rojas',
        telefono: null,
        estado: EstadoUsuario::ACTIVO,
        fechaIngreso: null,
    ));

    $actualizado = $repository->actualizar(new EncargadoPlanta(
        id: $guardado->id(),
        userId: $guardado->userId(),
        dni: $guardado->dni(),
        nombres: 'Ana María',
        apellidos: $guardado->apellidos(),
        telefono: '70001111',
        estado: EstadoUsuario::INACTIVO,
        fechaIngreso: $guardado->fechaIngreso(),
    ));

    expect($actualizado->nombres())->toBe('Ana María');
    expect($actualizado->telefono())->toBe('70001111');
    expect($actualizado->estado())->toBe(EstadoUsuario::INACTIVO);
});
