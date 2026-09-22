<?php

use App\Domain\Produccion\Insumo;
use App\Infrastructure\Persistence\Repositories\EloquentInsumoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('lista todos los insumos sin importar su estado, con la leche primero y el resto por nombre', function () {
    $repository = new EloquentInsumoRepository;

    $repository->guardar(new Insumo(null, 'Cuajo', 'ml', 250.0, 50.0, 'ACTIVO'));
    $repository->guardar(new Insumo(null, 'Fermento', 'kg', 4.0, 5.0, 'INACTIVO'));

    $todos = $repository->listar();

    // Además de estos dos, existe siempre el insumo del sistema LECHE.
    expect($todos)->toHaveCount(3);
    expect(array_map(fn (Insumo $i) => $i->nombre(), $todos))->toBe(['Leche', 'Cuajo', 'Fermento']);
});
