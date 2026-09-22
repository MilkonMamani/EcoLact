<?php

use App\Application\Recorrido\AbrirRecorrido;
use App\Application\Recorrido\IdentificarParada;
use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('identifica la parada correspondiente a un proveedor del recorrido por DNI', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, ordenVisita: 1);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);

    $parada = app(IdentificarParada::class)->execute($recorrido->id(), 'DNI', $proveedor->dni);

    expect($parada->proveedorId())->toBe($proveedor->id);
});

it('identifica la parada correspondiente a un proveedor del recorrido por su QR interno', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, ordenVisita: 1);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);

    $parada = app(IdentificarParada::class)->execute($recorrido->id(), 'QR', $proveedor->qr_token);

    expect($parada->proveedorId())->toBe($proveedor->id);
});

it('rechaza con un mensaje claro un DNI que no corresponde a ningún proveedor', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);

    app(IdentificarParada::class)->execute($recorrido->id(), 'DNI', '99999999');
})->throws(DomainException::class, 'No se encontró un proveedor con el identificador indicado.');

it('rechaza con un mensaje claro un proveedor que existe pero no pertenece a este recorrido', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
    $proveedorAjeno = fixture_proveedor($zona);

    app(IdentificarParada::class)->execute($recorrido->id(), 'DNI', $proveedorAjeno->dni);
})->throws(DomainException::class, 'Este proveedor no pertenece al recorrido actual.');
