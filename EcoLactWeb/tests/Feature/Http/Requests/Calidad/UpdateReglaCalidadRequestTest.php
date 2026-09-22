<?php

use App\Http\Requests\Calidad\UpdateReglaCalidadRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('acepta una actualización sin ningún campo (todo es opcional)', function () {
    $validador = crear_validador(UpdateReglaCalidadRequest::class, []);

    expect($validador->fails())->toBeFalse();
});

it('rechaza cuando valor_minimo y valor_maximo se envían juntos y el mínimo supera al máximo', function () {
    $validador = crear_validador(UpdateReglaCalidadRequest::class, [
        'valor_minimo' => 10.0,
        'valor_maximo' => 5.0,
    ]);

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('valor_minimo'))->toBeTrue();
});

it('acepta valor_minimo enviado explícitamente como null (limpiar el campo)', function () {
    $validador = crear_validador(UpdateReglaCalidadRequest::class, [
        'valor_minimo' => null,
    ]);

    expect($validador->fails())->toBeFalse();
});

it('acepta fecha_fin enviada explícitamente como null (reabrir vigencia)', function () {
    $validador = crear_validador(UpdateReglaCalidadRequest::class, [
        'fecha_fin' => null,
    ]);

    expect($validador->fails())->toBeFalse();
});

it('rechaza un resultado_incumplimiento que no es un valor permitido', function () {
    $validador = crear_validador(UpdateReglaCalidadRequest::class, [
        'resultado_incumplimiento' => 'DUDOSA',
    ]);

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('resultado_incumplimiento'))->toBeTrue();
});

it('no admite el campo parametro_id (no es editable en la actualización)', function () {
    $reglas = (new UpdateReglaCalidadRequest)->rules();

    expect($reglas)->not->toHaveKey('parametro_id');
});
