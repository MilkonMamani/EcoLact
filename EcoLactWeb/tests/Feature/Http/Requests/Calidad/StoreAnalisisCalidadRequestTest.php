<?php

use App\Http\Requests\Calidad\StoreAnalisisCalidadRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function analisisCalidadRequestData(array $overrides = []): array
{
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    return array_merge([
        'entrega_id' => $entrega->id,
        'valores' => ['GRASA' => 3.5, 'DENSIDAD' => 1.030],
    ], $overrides);
}

it('rechaza valores de parámetros que no son numéricos', function () {
    $validador = crear_validador(StoreAnalisisCalidadRequest::class, analisisCalidadRequestData([
        'valores' => ['GRASA' => 'alto', 'DENSIDAD' => 1.030],
    ]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('valores.GRASA'))->toBeTrue();
});

it('acepta valores de parámetros numéricos', function () {
    $validador = crear_validador(StoreAnalisisCalidadRequest::class, analisisCalidadRequestData([
        'valores' => ['GRASA' => 3.5, 'DENSIDAD' => 1.030, 'PH' => 6.7],
    ]));

    expect($validador->fails())->toBeFalse();
});

it('rechaza un análisis sin ningún valor de parámetro', function () {
    $validador = crear_validador(StoreAnalisisCalidadRequest::class, analisisCalidadRequestData(['valores' => []]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('valores'))->toBeTrue();
});

it('rechaza una entrega_id que no existe', function () {
    $validador = crear_validador(StoreAnalisisCalidadRequest::class, analisisCalidadRequestData(['entrega_id' => 999999]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('entrega_id'))->toBeTrue();
});
