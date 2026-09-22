<?php

use App\Application\Recorrido\AbrirRecorrido;
use App\Application\Recorrido\MarcarNoEntrego;
use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('marca una parada como "no entregó" sin crear ninguna entrega', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, ordenVisita: 1);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
    $parada = RecorridoDetalleModel::where('recorrido_id', $recorrido->id())->first();

    $actualizada = app(MarcarNoEntrego::class)->execute($parada->id, $acopiador->user_id, 'El proveedor no tenía leche disponible hoy.');

    expect($actualizada->estado())->toBe(EstadoParadaRecorrido::NO_ENTREGO);
    expect($actualizada->observacion())->toBe('El proveedor no tenía leche disponible hoy.');
    $this->assertDatabaseMissing('entregas', ['recorrido_detalle_id' => $parada->id]);
});

it('exige un motivo no vacío para marcar "no entregó"', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, ordenVisita: 1);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
    $parada = RecorridoDetalleModel::where('recorrido_id', $recorrido->id())->first();

    app(MarcarNoEntrego::class)->execute($parada->id, $acopiador->user_id, '   ');
})->throws(DomainException::class, 'El motivo de "no entregó" es obligatorio.');
