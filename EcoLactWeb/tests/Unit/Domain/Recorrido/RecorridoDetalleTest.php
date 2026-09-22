<?php

use App\Domain\Recorrido\RecorridoDetalle;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;

function crearParada(?float $lat, ?float $lng): RecorridoDetalle
{
    return new RecorridoDetalle(
        id: null,
        recorridoId: 1,
        proveedorId: 1,
        rutaDetalleId: 1,
        ordenVisita: 1,
        latitud: $lat,
        longitud: $lng,
        referenciaUbicacion: null,
        estado: EstadoParadaRecorrido::PENDIENTE,
    );
}

it('acepta una parada sin coordenadas (proveedor sin ubicación registrada)', function () {
    $parada = crearParada(null, null);

    expect($parada->tieneUbicacion())->toBeFalse();
});

it('acepta una parada con coordenadas válidas dentro de rango', function () {
    $parada = crearParada(-12.5, -70.1);

    expect($parada->tieneUbicacion())->toBeTrue();
});

it('rechaza indicar solo la latitud sin la longitud', function () {
    crearParada(-12.5, null);
})->throws(InvalidArgumentException::class, 'La latitud y la longitud deben indicarse juntas o dejarse ambas vacías.');

it('rechaza una latitud fuera de rango', function () {
    crearParada(95.0, -70.1);
})->throws(InvalidArgumentException::class, 'La latitud debe estar entre -90 y 90.');

it('rechaza una longitud fuera de rango', function () {
    crearParada(-12.5, 200.0);
})->throws(InvalidArgumentException::class, 'La longitud debe estar entre -180 y 180.');

it('rechaza un orden de visita menor a 1', function () {
    new RecorridoDetalle(
        id: null,
        recorridoId: 1,
        proveedorId: 1,
        rutaDetalleId: null,
        ordenVisita: 0,
        latitud: null,
        longitud: null,
        referenciaUbicacion: null,
        estado: EstadoParadaRecorrido::PENDIENTE,
    );
})->throws(InvalidArgumentException::class, 'El orden de visita debe ser un entero positivo.');
