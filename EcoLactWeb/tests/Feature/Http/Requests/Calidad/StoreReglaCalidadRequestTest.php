<?php

use App\Http\Requests\Calidad\StoreReglaCalidadRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function reglaCalidadRequestData(array $overrides = []): array
{
    $parametro = fixture_parametro('GRASA');

    return array_merge([
        'parametro_id' => $parametro->id,
        'valor_minimo' => 3.0,
        'valor_maximo' => 6.0,
        'resultado_incumplimiento' => 'OBSERVADA',
        'fecha_inicio' => '2026-01-01',
        'estado' => 'ACTIVO',
    ], $overrides);
}

it('rechaza una regla donde el valor mínimo es mayor que el valor máximo', function () {
    $validador = crear_validador(StoreReglaCalidadRequest::class, reglaCalidadRequestData([
        'valor_minimo' => 10.0,
        'valor_maximo' => 5.0,
    ]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('valor_minimo'))->toBeTrue();
});

it('acepta una regla donde el valor mínimo es menor o igual al valor máximo', function () {
    $validador = crear_validador(StoreReglaCalidadRequest::class, reglaCalidadRequestData([
        'valor_minimo' => 3.0,
        'valor_maximo' => 6.0,
    ]));

    expect($validador->fails())->toBeFalse();
});

it('acepta una regla con solo valor mínimo o solo valor máximo informado', function () {
    $validador = crear_validador(StoreReglaCalidadRequest::class, reglaCalidadRequestData([
        'valor_minimo' => null,
        'valor_maximo' => 6.0,
    ]));

    expect($validador->fails())->toBeFalse();
});

it('rechaza un resultado_incumplimiento que no es un valor permitido', function () {
    $validador = crear_validador(StoreReglaCalidadRequest::class, reglaCalidadRequestData([
        'resultado_incumplimiento' => 'DUDOSA',
    ]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('resultado_incumplimiento'))->toBeTrue();
});

it('rechaza un parametro_id que no existe', function () {
    $validador = crear_validador(StoreReglaCalidadRequest::class, reglaCalidadRequestData(['parametro_id' => 999999]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('parametro_id'))->toBeTrue();
});
