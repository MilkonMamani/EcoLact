<?php

use App\Domain\Produccion\LoteProduccion;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Repositories\EloquentLoteProduccionRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearUsuarioProduccion(): User
{
    $role = RoleModel::create(['nombre' => 'PRODUCCION', 'descripcion' => null]);

    return User::create([
        'role_id' => $role->id,
        'username' => 'produccion.test',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
}

it('guarda un lote de producción y lo mapea con fin nulo', function () {
    $repository = new EloquentLoteProduccionRepository;
    $user = crearUsuarioProduccion();

    $lote = new LoteProduccion(
        id: null,
        codigo: 'LOTE-0001',
        fechaHoraInicio: new DateTimeImmutable('2026-08-01 06:00:00'),
        fechaHoraFin: null,
        estado: 'EN_PROCESO',
        registradoPorUserId: $user->id,
    );

    $guardado = $repository->guardar($lote);

    expect($guardado->id())->not->toBeNull();
    expect($guardado->fechaHoraFin())->toBeNull();

    expect($repository->buscarPorCodigo('LOTE-0001')?->id())->toBe($guardado->id());
    expect($repository->listarPorEstado('EN_PROCESO'))->toHaveCount(1);
});

it('actualiza un lote de producción marcando su fin', function () {
    $repository = new EloquentLoteProduccionRepository;
    $user = crearUsuarioProduccion();

    $guardado = $repository->guardar(new LoteProduccion(
        id: null,
        codigo: 'LOTE-0002',
        fechaHoraInicio: new DateTimeImmutable('2026-08-02 06:00:00'),
        fechaHoraFin: null,
        estado: 'EN_PROCESO',
        registradoPorUserId: $user->id,
    ));

    $actualizado = $repository->actualizar(new LoteProduccion(
        id: $guardado->id(),
        codigo: 'LOTE-0002',
        fechaHoraInicio: new DateTimeImmutable('2026-08-02 06:00:00'),
        fechaHoraFin: new DateTimeImmutable('2026-08-02 10:00:00'),
        estado: 'FINALIZADO',
        registradoPorUserId: $user->id,
    ));

    expect($actualizado->estado())->toBe('FINALIZADO');
    expect($actualizado->fechaHoraFin()?->format('Y-m-d H:i:s'))->toBe('2026-08-02 10:00:00');
});
