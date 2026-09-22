<?php

use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\LotesProduccionFiltro;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Queries\EloquentLotesProduccionListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function elpq_filtroVacio(): LotesProduccionFiltro
{
    return LotesProduccionFiltro::resolver(null, null, null, null, null);
}

it('calcula los KPIs y arma las filas de lotes de producción', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = fixture_producto('Queso Test');

    fixture_lote(30.0, $admin->id, 'EN_PROCESO');
    $finalizado = fixture_lote(20.0, $admin->id, 'EN_PROCESO');
    ProduccionDetalleModel::create([
        'lote_produccion_id' => $finalizado->id(), 'producto_id' => $producto->id,
        'cantidad_planificada' => 6.0, 'cantidad_obtenida' => null,
    ]);
    app(FinalizarProduccion::class)->execute(
        loteId: $finalizado->id(),
        cantidadesObtenidas: [ProduccionDetalleModel::first()->id => 5.0],
        registradoPorUserId: $admin->id,
    );

    $datos = (new EloquentLotesProduccionListadoQuery)->obtener(elpq_filtroVacio());

    expect($datos->kpis['enProceso'])->toBe(1);
    expect($datos->kpis['finalizados'])->toBe(1);
    expect($datos->kpis['lecheUtilizada'])->toBe(50.0);
    expect($datos->kpis['productosObtenidos'])->toBe(5.0);
    expect($datos->filas)->toHaveCount(2);

    $filaFinalizada = collect($datos->filas)->firstWhere('estado', 'FINALIZADO');
    expect($filaFinalizada['productos'])->toBe(1);
    expect($filaFinalizada['obtenido'])->toBe(5.0);
    expect($filaFinalizada['litros'])->toBe(20.0);
    expect($filaFinalizada['fin'])->not->toBeNull();

    $filaEnProceso = collect($datos->filas)->firstWhere('estado', 'EN_PROCESO');
    expect($filaEnProceso['fin'])->toBeNull();
    expect($filaEnProceso['obtenido'])->toBeNull();
    expect($filaEnProceso['litros'])->toBe(30.0);
});

it('filtra por búsqueda de código y por estado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $lote = fixture_lote(30.0, $admin->id);
    fixture_lote(10.0, $admin->id, 'FINALIZADO');

    $porBusqueda = LotesProduccionFiltro::resolver($lote->codigo(), null, null, null, null);
    expect((new EloquentLotesProduccionListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);

    $porEstado = LotesProduccionFiltro::resolver(null, 'FINALIZADO', null, null, null);
    expect((new EloquentLotesProduccionListadoQuery)->obtener($porEstado)->filas)->toHaveCount(1);
});
