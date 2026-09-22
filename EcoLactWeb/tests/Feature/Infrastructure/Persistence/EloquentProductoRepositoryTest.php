<?php

use App\Domain\Produccion\Producto;
use App\Infrastructure\Persistence\Repositories\EloquentProductoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('guarda, actualiza y busca productos', function () {
    $repository = new EloquentProductoRepository;

    $guardado = $repository->guardar(new Producto(null, 'Queso Fresco', 'kg', 'ACTIVO'));

    expect($guardado->id())->not->toBeNull();

    $actualizado = $repository->actualizar(new Producto(
        id: $guardado->id(),
        nombre: 'Queso Fresco',
        unidadMedida: 'kg',
        estado: 'INACTIVO',
    ));
    expect($actualizado->estaActivo())->toBeFalse();

    $porNombre = $repository->buscarPorNombre('Queso Fresco');
    expect($porNombre?->id())->toBe($guardado->id());
});

it('lista solo los productos activos', function () {
    $repository = new EloquentProductoRepository;

    $repository->guardar(new Producto(null, 'Yogurt', 'l', 'ACTIVO'));
    $repository->guardar(new Producto(null, 'Manjar', 'kg', 'INACTIVO'));

    $activos = $repository->listarActivos();

    expect($activos)->toHaveCount(1);
    expect($activos[0]->nombre())->toBe('Yogurt');
});

it('lista todos los productos sin importar su estado, ordenados por nombre', function () {
    $repository = new EloquentProductoRepository;

    $repository->guardar(new Producto(null, 'Yogurt', 'l', 'ACTIVO'));
    $repository->guardar(new Producto(null, 'Manjar', 'kg', 'INACTIVO'));

    $todos = $repository->listar();

    expect($todos)->toHaveCount(2);
    expect($todos[0]->nombre())->toBe('Manjar');
    expect($todos[1]->nombre())->toBe('Yogurt');
});
