<?php

use App\Application\Acopiador\AcopiadoresFiltro;
use App\Infrastructure\Persistence\Queries\EloquentAcopiadoresListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function eaq_filtroVacio(): AcopiadoresFiltro
{
    return AcopiadoresFiltro::resolver(null, null, null);
}

it('calcula los KPIs y arma las filas con zona y conteo de entregas', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $conEntregas = fixture_acopiador($zonaNorte, '11112222');
    $sinEntregas = fixture_acopiador($zonaSur, '33334444');
    $sinEntregas->update(['estado' => 'INACTIVO']);
    $proveedor = fixture_proveedor($zonaNorte);
    fixture_entrega($proveedor, $conEntregas, 30.0);
    fixture_entrega($proveedor, $conEntregas, 20.0);

    $datos = (new EloquentAcopiadoresListadoQuery)->obtener(eaq_filtroVacio());

    expect($datos->kpis['activos'])->toBe(1);
    expect($datos->kpis['inactivos'])->toBe(1);
    expect($datos->kpis['zonasAsignadas'])->toBe(2);
    expect($datos->kpis['conEntregas'])->toBe(1);
    expect($datos->filas)->toHaveCount(2);

    $fila = collect($datos->filas)->firstWhere('dni', '11112222');
    expect($fila['zonaNombre'])->toBe('Norte');
    expect($fila['entregas'])->toBe(2);
});

it('filtra por estado, zona y búsqueda', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    fixture_acopiador($zonaNorte, '55556666');
    $inactivo = fixture_acopiador($zonaSur, '77778888');
    $inactivo->update(['estado' => 'INACTIVO']);

    $porEstado = AcopiadoresFiltro::resolver(null, 'INACTIVO', null);
    expect((new EloquentAcopiadoresListadoQuery)->obtener($porEstado)->filas)->toHaveCount(1);

    $porZona = AcopiadoresFiltro::resolver(null, null, $zonaNorte->id);
    expect((new EloquentAcopiadoresListadoQuery)->obtener($porZona)->filas)->toHaveCount(1);

    $porBusqueda = AcopiadoresFiltro::resolver('55556666', null, null);
    expect((new EloquentAcopiadoresListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);
});
