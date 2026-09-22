<?php

use App\Application\Recorrido\AbrirRecorrido;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\RecorridoAcopioModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('el acopiador dueño del recorrido puede verlo y operarlo', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
    $modelo = RecorridoAcopioModel::find($recorrido->id());

    expect($acopiador->user->can('view', $modelo))->toBeTrue();
    expect($acopiador->user->can('update', $modelo))->toBeTrue();
});

it('un acopiador ajeno NO puede ver ni operar el recorrido de otro, aunque conozca su ID', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiadorDueño = fixture_acopiador($zona);
    $acopiadorAjeno = fixture_acopiador($zona, '87654321');
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiadorDueño->id);
    $modelo = RecorridoAcopioModel::find($recorrido->id());

    expect($acopiadorAjeno->user->can('view', $modelo))->toBeFalse();
    expect($acopiadorAjeno->user->can('update', $modelo))->toBeFalse();
});

it('un PROVEEDOR no puede ver ni operar ningún recorrido', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
    $modelo = RecorridoAcopioModel::find($recorrido->id());
    $proveedor = fixture_proveedor($zona);

    expect($proveedor->user->can('view', $modelo))->toBeFalse();
});

it('ADMINISTRADOR puede ver cualquier recorrido vía Gate::before', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
    $modelo = RecorridoAcopioModel::find($recorrido->id());
    $admin = fixture_usuario('ADMINISTRADOR');

    expect($admin->can('view', $modelo))->toBeTrue();
});
