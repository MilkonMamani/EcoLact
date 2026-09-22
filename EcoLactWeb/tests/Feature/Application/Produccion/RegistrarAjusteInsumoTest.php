<?php

use App\Application\Produccion\RegistrarAjusteInsumo;
use App\Domain\Produccion\Insumo;
use App\Infrastructure\Persistence\Repositories\EloquentInsumoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

it('ajusta el stock de un insumo hacia abajo y registra una SALIDA con motivo AJUSTE_SALIDA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $insumo = (new EloquentInsumoRepository)->guardar(new Insumo(null, 'Cuajo', 'ml', 250.0, 50.0, 'ACTIVO'));

    $movimiento = app(RegistrarAjusteInsumo::class)->execute(
        insumoId: $insumo->id(),
        nuevoStock: 220.0,
        motivo: 'Diferencia encontrada en inventario',
        registradoPorUserId: $admin->id,
    );

    expect($movimiento->tipo())->toBe('SALIDA');
    expect($movimiento->motivo())->toBe('AJUSTE_SALIDA');
    expect($movimiento->cantidad())->toBe(30.0);

    $actualizado = (new EloquentInsumoRepository)->buscarPorId($insumo->id());
    expect($actualizado->stockActual())->toBe(220.0);
});

it('ajusta el stock de un insumo hacia arriba y registra una ENTRADA con motivo AJUSTE_ENTRADA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $insumo = (new EloquentInsumoRepository)->guardar(new Insumo(null, 'Fermento', 'kg', 4.0, 5.0, 'ACTIVO'));

    $movimiento = app(RegistrarAjusteInsumo::class)->execute(
        insumoId: $insumo->id(),
        nuevoStock: 12.0,
        motivo: 'Conteo físico',
        registradoPorUserId: $admin->id,
    );

    expect($movimiento->tipo())->toBe('ENTRADA');
    expect($movimiento->motivo())->toBe('AJUSTE_ENTRADA');
    expect($movimiento->cantidad())->toBe(8.0);
});

it('rechaza un ajuste de insumo que no cambia el stock actual', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $insumo = (new EloquentInsumoRepository)->guardar(new Insumo(null, 'Sal', 'kg', 10.0, 2.0, 'ACTIVO'));

    app(RegistrarAjusteInsumo::class)->execute(
        insumoId: $insumo->id(),
        nuevoStock: 10.0,
        motivo: 'x',
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'El nuevo stock es igual al stock actual: no hay nada que ajustar.');

it('rechaza el ajuste de un insumo que no existe', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    app(RegistrarAjusteInsumo::class)->execute(
        insumoId: 999999,
        nuevoStock: 5.0,
        motivo: 'x',
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'El insumo indicado no existe.');
