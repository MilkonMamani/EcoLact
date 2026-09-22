<?php

use App\Domain\Produccion\UnidadMedida;

it('convierte dentro de la misma familia con factores exactos', function () {
    expect(UnidadMedida::convertir(200, 'g', 'kg'))->toBe(0.2);
    expect(UnidadMedida::convertir(1, 'kg', 'g'))->toBe(1000.0);
    expect(UnidadMedida::convertir(5, 'ml', 'L'))->toBe(0.005);
    expect(UnidadMedida::convertir(2, 'L', 'ml'))->toBe(2000.0);
    expect(UnidadMedida::convertir(7, 'unidad', 'unidad'))->toBe(7.0);
});

it('no convierte entre magnitudes distintas', function () {
    UnidadMedida::convertir(1, 'kg', 'L');
})->throws(InvalidArgumentException::class, 'magnitudes distintas');

it('rechaza unidades desconocidas', function () {
    UnidadMedida::convertir(1, 'libras', 'kg');
})->throws(InvalidArgumentException::class);

it('lista solo las unidades compatibles con la base de un insumo', function () {
    expect(array_keys(UnidadMedida::opcionesCompatiblesCon('g')))->toBe(['kg', 'g']);
    expect(array_keys(UnidadMedida::opcionesCompatiblesCon('L')))->toBe(['L', 'ml']);
    expect(array_keys(UnidadMedida::opcionesCompatiblesCon('unidad')))->toBe(['unidad']);
});
