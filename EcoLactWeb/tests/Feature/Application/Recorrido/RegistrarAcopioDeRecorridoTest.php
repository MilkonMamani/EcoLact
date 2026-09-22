<?php

use App\Application\Recorrido\AbrirRecorrido;
use App\Application\Recorrido\FinalizarRecorrido;
use App\Application\Recorrido\RegistrarAcopioDeRecorrido;
use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function prepararRecorridoConUnaParada(): array
{
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, ordenVisita: 1);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
    $parada = RecorridoDetalleModel::where('recorrido_id', $recorrido->id())->first();

    return [$recorrido, $parada, $acopiador, $proveedor];
}

it('registra el acopio de una parada y la marca como recolectada, en una sola operación', function () {
    [, $parada, $acopiador] = prepararRecorridoConUnaParada();

    $resultado = app(RegistrarAcopioDeRecorrido::class)->execute(
        recorridoDetalleId: $parada->id,
        acopiadorUserId: $acopiador->user_id,
        litros: 25.5,
        tokenIdempotencia: 'token-001',
    );

    expect($resultado->entrega->litros())->toBe(25.5);
    expect($resultado->parada->estado())->toBe(EstadoParadaRecorrido::RECOLECTADO);
    expect(RecorridoDetalleModel::find($parada->id)->estado)->toBe('RECOLECTADO');
    $this->assertDatabaseHas('entregas', ['recorrido_detalle_id' => $parada->id, 'token_idempotencia' => 'token-001']);
});

it('no marca la parada como recolectada si falla el guardado (litros inválidos)', function () {
    [, $parada, $acopiador] = prepararRecorridoConUnaParada();

    try {
        app(RegistrarAcopioDeRecorrido::class)->execute(
            recorridoDetalleId: $parada->id,
            acopiadorUserId: $acopiador->user_id,
            litros: -5,
            tokenIdempotencia: 'token-invalido',
        );
    } catch (InvalidArgumentException) {
        // esperado
    }

    expect(RecorridoDetalleModel::find($parada->id)->estado)->toBe('PENDIENTE');
    $this->assertDatabaseMissing('entregas', ['recorrido_detalle_id' => $parada->id]);
});

it('reintentar con el mismo token de idempotencia no crea una segunda entrega', function () {
    [, $parada, $acopiador] = prepararRecorridoConUnaParada();

    $primero = app(RegistrarAcopioDeRecorrido::class)->execute(
        recorridoDetalleId: $parada->id, acopiadorUserId: $acopiador->user_id, litros: 10, tokenIdempotencia: 'mismo-token',
    );
    $segundo = app(RegistrarAcopioDeRecorrido::class)->execute(
        recorridoDetalleId: $parada->id, acopiadorUserId: $acopiador->user_id, litros: 10, tokenIdempotencia: 'mismo-token',
    );

    expect($segundo->entrega->id())->toBe($primero->entrega->id());
    $this->assertDatabaseCount('entregas', 1);
});

it('permite registrar más de una entrega legítima para la misma parada con tokens distintos', function () {
    [, $parada, $acopiador] = prepararRecorridoConUnaParada();

    app(RegistrarAcopioDeRecorrido::class)->execute(recorridoDetalleId: $parada->id, acopiadorUserId: $acopiador->user_id, litros: 10, tokenIdempotencia: 'token-a');
    app(RegistrarAcopioDeRecorrido::class)->execute(recorridoDetalleId: $parada->id, acopiadorUserId: $acopiador->user_id, litros: 8, tokenIdempotencia: 'token-b');

    $this->assertDatabaseCount('entregas', 2);
});

it('un acopiador ajeno al recorrido no puede registrar acopio en él', function () {
    [, $parada] = prepararRecorridoConUnaParada();
    $otraZona = fixture_zona('Otra zona');
    $otroAcopiador = fixture_acopiador($otraZona);

    app(RegistrarAcopioDeRecorrido::class)->execute(
        recorridoDetalleId: $parada->id, acopiadorUserId: $otroAcopiador->user_id, litros: 10, tokenIdempotencia: 'token-ajeno',
    );
})->throws(DomainException::class, 'No tienes permiso para registrar acopio en este recorrido.');

it('un recorrido finalizado ya no admite nuevos registros', function () {
    [$recorrido, $parada, $acopiador] = prepararRecorridoConUnaParada();
    app(FinalizarRecorrido::class)->execute($recorrido->id(), $acopiador->id);

    app(RegistrarAcopioDeRecorrido::class)->execute(
        recorridoDetalleId: $parada->id, acopiadorUserId: $acopiador->user_id, litros: 10, tokenIdempotencia: 'token-finalizado',
    );
})->throws(DomainException::class, 'Este recorrido ya no admite nuevos registros.');
