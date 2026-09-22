<?php

use App\Http\Requests\Entrega\StoreEntregaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function entregaRequestData(array $overrides = []): array
{
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    return array_merge([
        'metodo_identificacion' => 'DNI',
        'identificador' => '12345678',
        'acopiador_user_id' => $acopiador->user_id,
        'litros' => 30.5,
    ], $overrides);
}

it('rechaza una entrega con litros igual a cero', function () {
    $validador = crear_validador(StoreEntregaRequest::class, entregaRequestData(['litros' => 0]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('litros'))->toBeTrue();
});

it('rechaza una entrega con litros negativos', function () {
    $validador = crear_validador(StoreEntregaRequest::class, entregaRequestData(['litros' => -5]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('litros'))->toBeTrue();
});

it('acepta una entrega con litros válidos', function () {
    $validador = crear_validador(StoreEntregaRequest::class, entregaRequestData(['litros' => 25.75]));

    expect($validador->fails())->toBeFalse();
});

it('rechaza un método de identificación no soportado', function () {
    $validador = crear_validador(StoreEntregaRequest::class, entregaRequestData(['metodo_identificacion' => 'CARNET']));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('metodo_identificacion'))->toBeTrue();
});

it('rechaza un identificador DNI mal formado cuando el método es DNI', function () {
    $validador = crear_validador(StoreEntregaRequest::class, entregaRequestData([
        'metodo_identificacion' => 'DNI',
        'identificador' => '123',
    ]));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('identificador'))->toBeTrue();
});

it('acepta un identificador QR sin exigirle el formato de DNI', function () {
    $validador = crear_validador(StoreEntregaRequest::class, entregaRequestData([
        'metodo_identificacion' => 'QR',
        'identificador' => fixture_qr_token(),
    ]));

    expect($validador->fails())->toBeFalse();
});
