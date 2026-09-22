<?php

use App\Domain\Zona\Zona;
use App\Infrastructure\Persistence\Repositories\EloquentZonaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('guarda una zona y la recupera mapeada a la entidad de dominio', function () {
    $repository = new EloquentZonaRepository;

    $zona = new Zona(
        id: null,
        nombre: 'Zona Norte',
        descripcion: 'Comunidades del norte',
        estado: 'ACTIVO',
    );

    $guardada = $repository->guardar($zona);

    expect($guardada)->toBeInstanceOf(Zona::class);
    expect($guardada->id())->not->toBeNull();
    expect($guardada->nombre())->toBe('Zona Norte');

    $encontrada = $repository->buscarPorId($guardada->id());
    expect($encontrada)->not->toBeNull();
    expect($encontrada->nombre())->toBe('Zona Norte');
    expect($encontrada->descripcion())->toBe('Comunidades del norte');
    expect($encontrada->estaActiva())->toBeTrue();

    $porNombre = $repository->buscarPorNombre('Zona Norte');
    expect($porNombre?->id())->toBe($guardada->id());
});

it('actualiza una zona existente', function () {
    $repository = new EloquentZonaRepository;

    $guardada = $repository->guardar(new Zona(null, 'Zona Sur', null, 'ACTIVO'));

    $actualizada = $repository->actualizar(new Zona(
        id: $guardada->id(),
        nombre: 'Zona Sur',
        descripcion: 'Actualizada',
        estado: 'INACTIVO',
    ));

    expect($actualizada->descripcion())->toBe('Actualizada');
    expect($actualizada->estaActiva())->toBeFalse();
});

it('lista únicamente las zonas activas', function () {
    $repository = new EloquentZonaRepository;

    $repository->guardar(new Zona(null, 'Zona Activa', null, 'ACTIVO'));
    $repository->guardar(new Zona(null, 'Zona Inactiva', null, 'INACTIVO'));

    $activas = $repository->listarActivas();

    expect($activas)->toHaveCount(1);
    expect($activas[0])->toBeInstanceOf(Zona::class);
    expect($activas[0]->nombre())->toBe('Zona Activa');
});

it('no encuentra una zona inexistente', function () {
    $repository = new EloquentZonaRepository;

    expect($repository->buscarPorId(999))->toBeNull();
    expect($repository->buscarPorNombre('No Existe'))->toBeNull();
});
