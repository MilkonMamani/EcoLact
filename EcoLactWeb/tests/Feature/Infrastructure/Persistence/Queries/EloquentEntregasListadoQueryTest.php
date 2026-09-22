<?php

use App\Application\Acopio\EntregasFiltro;
use App\Infrastructure\Persistence\Queries\EloquentEntregasListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function eelq_filtroVacio(): EntregasFiltro
{
    return EntregasFiltro::resolver(null, null, null, null, null, null, null);
}

it('calcula los KPIs y arma las filas con proveedor, zona y acopiador', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $proveedorNorte = fixture_proveedor($zonaNorte, '11112222');
    $proveedorSur = fixture_proveedor($zonaSur, '33334444');
    $acopiadorNorte = fixture_acopiador($zonaNorte);
    $acopiadorSur = fixture_acopiador($zonaSur);

    $entregaPendiente = fixture_entrega($proveedorNorte, $acopiadorNorte, 30.0);
    $entregaAnalizada = fixture_entrega($proveedorSur, $acopiadorSur, 20.0);
    $entregaAnalizada->update(['estado' => 'ANALIZADA']);

    $datos = (new EloquentEntregasListadoQuery)->obtener(eelq_filtroVacio());

    expect($datos->kpis['entregas'])->toBe(2);
    expect($datos->kpis['litrosRegistrados'])->toBe(50.0);
    expect($datos->kpis['pendientesAnalisis'])->toBe(1);
    expect($datos->kpis['analizadas'])->toBe(1);
    expect($datos->filas)->toHaveCount(2);
    expect($datos->zonas)->toHaveCount(2);
    expect($datos->acopiadores)->toHaveCount(2);

    $filaPendiente = collect($datos->filas)->firstWhere('proveedorDni', '11112222');
    expect($filaPendiente['zonaNombre'])->toBe('Norte');
    expect($filaPendiente['acopiadorNombre'])->not->toBe('—');
});

it('excluye entregas ANULADAS de los KPIs y filtra por zona, acopiador y estado', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $proveedorNorte = fixture_proveedor($zonaNorte, '55556666');
    $proveedorSur = fixture_proveedor($zonaSur, '77778888');
    $acopiadorNorte = fixture_acopiador($zonaNorte);
    $acopiadorSur = fixture_acopiador($zonaSur);

    $entregaNorte = fixture_entrega($proveedorNorte, $acopiadorNorte, 30.0);
    $entregaAnulada = fixture_entrega($proveedorSur, $acopiadorSur, 999.0);
    $entregaAnulada->update(['estado' => 'ANULADA']);

    $datos = (new EloquentEntregasListadoQuery)->obtener(eelq_filtroVacio());
    expect($datos->kpis['litrosRegistrados'])->toBe(30.0);
    expect($datos->kpis['entregas'])->toBe(1);

    $porZona = EntregasFiltro::resolver(null, $zonaNorte->id, null, null, null, null, null);
    $filasPorZona = (new EloquentEntregasListadoQuery)->obtener($porZona)->filas;
    expect($filasPorZona)->toHaveCount(1);
    expect($filasPorZona[0]['zonaNombre'])->toBe('Norte');

    $porAcopiador = EntregasFiltro::resolver(null, null, $acopiadorNorte->id, null, null, null, null);
    expect((new EloquentEntregasListadoQuery)->obtener($porAcopiador)->filas)->toHaveCount(1);

    $porEstado = EntregasFiltro::resolver(null, null, null, 'ANULADA', null, null, null);
    $filasAnuladas = (new EloquentEntregasListadoQuery)->obtener($porEstado)->filas;
    expect($filasAnuladas)->toHaveCount(1);
    expect($filasAnuladas[0]['proveedorDni'])->toBe('77778888');

    $porBusqueda = EntregasFiltro::resolver('55556666', null, null, null, null, null, null);
    expect((new EloquentEntregasListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);
});
