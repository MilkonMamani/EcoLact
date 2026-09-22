<?php

use App\Http\Requests\Proveedor\StoreProveedorRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function proveedorRequestData(array $overrides = []): array
{
    return array_merge([
        'dni' => '12345678',
        'nombres' => 'Ana',
        'apellidos' => 'Quispe',
        'telefono' => '70000000',
        'username' => 'ana.quispe',
        'password' => 'Secreto#123',
    ], $overrides);
}

it('rechaza un DNI con menos de 8 dígitos', function () {
    $validador = crear_validador(StoreProveedorRequest::class, proveedorRequestData(['dni' => '1234567']));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('dni'))->toBeTrue();
});

it('rechaza un DNI con caracteres no numéricos', function () {
    $validador = crear_validador(StoreProveedorRequest::class, proveedorRequestData(['dni' => '1234567A']));

    expect($validador->fails())->toBeTrue();
    expect($validador->errors()->has('dni'))->toBeTrue();
});

it('acepta un DNI de exactamente 8 dígitos', function () {
    $validador = crear_validador(StoreProveedorRequest::class, proveedorRequestData(['dni' => '87654321']));

    expect($validador->fails())->toBeFalse();
});

it('rechaza campos obligatorios ausentes', function () {
    $validador = crear_validador(StoreProveedorRequest::class, []);

    expect($validador->fails())->toBeTrue();
    foreach (['dni', 'nombres', 'apellidos', 'username', 'password'] as $campo) {
        expect($validador->errors()->has($campo))->toBeTrue();
    }
});

it('acepta un registro sin coordenadas: el proveedor se crea independientemente de su zona', function () {
    $validador = crear_validador(StoreProveedorRequest::class, proveedorRequestData());

    expect($validador->fails())->toBeFalse();
});

it('ignora las coordenadas: la ubicación ya no es un dato del proveedor sino de su asignación a una ruta', function () {
    $validador = crear_validador(StoreProveedorRequest::class, proveedorRequestData([
        'latitud' => 95,
        'longitud' => 185,
        'referencia_ubicacion' => 'Frente a la escuela',
    ]));

    expect($validador->fails())->toBeFalse();
    expect($validador->validated())->not->toHaveKeys(['latitud', 'longitud', 'referencia_ubicacion']);
});
