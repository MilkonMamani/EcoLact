<?php

use App\Domain\Ruta\RutaDetalle;

function crearDetalle(?float $lat = null, ?float $lng = null, int $orden = 1): RutaDetalle
{
    return new RutaDetalle(
        id: 1,
        rutaId: 1,
        proveedorId: 1,
        ordenVisita: $orden,
        latitud: $lat,
        longitud: $lng,
        referenciaUbicacion: null,
    );
}

it('acepta una asignación sin ubicación', function () {
    expect(crearDetalle()->tieneUbicacion())->toBeFalse();
});

it('acepta coordenadas válidas dentro de rango', function () {
    expect(crearDetalle(-15.84, -70.02)->tieneUbicacion())->toBeTrue();
});

it('rechaza una latitud fuera de -90 a 90', function () {
    crearDetalle(95.0, -70.0);
})->throws(InvalidArgumentException::class, 'La latitud debe estar entre -90 y 90.');

it('rechaza una longitud fuera de -180 a 180', function () {
    crearDetalle(-15.0, 185.0);
})->throws(InvalidArgumentException::class, 'La longitud debe estar entre -180 y 180.');

it('rechaza indicar solo una de las dos coordenadas', function () {
    crearDetalle(-15.0, null);
})->throws(InvalidArgumentException::class, 'La latitud y la longitud deben indicarse juntas o dejarse ambas vacías.');

it('rechaza un orden de visita no positivo', function () {
    crearDetalle(orden: 0);
})->throws(InvalidArgumentException::class, 'El orden de visita debe ser un entero positivo.');

it('conUbicacion devuelve una copia con la nueva ubicación sin alterar ruta, proveedor ni orden', function () {
    $original = crearDetalle(orden: 3);

    $editado = $original->conUbicacion(-15.5, -70.5, 'Frente a la escuela');

    expect($editado->latitud())->toBe(-15.5)
        ->and($editado->longitud())->toBe(-70.5)
        ->and($editado->referenciaUbicacion())->toBe('Frente a la escuela')
        ->and($editado->ordenVisita())->toBe(3)
        ->and($editado->rutaId())->toBe($original->rutaId())
        ->and($original->tieneUbicacion())->toBeFalse();
});
