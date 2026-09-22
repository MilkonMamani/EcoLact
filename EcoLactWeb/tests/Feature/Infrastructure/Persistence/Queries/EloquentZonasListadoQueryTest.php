<?php

use App\Application\Zona\ZonasFiltro;
use App\Infrastructure\Persistence\Queries\EloquentZonasListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function ezq_filtroVacio(): ZonasFiltro
{
    return ZonasFiltro::resolver(null, null);
}

it('calcula los KPIs y arma las filas con conteo de proveedores y acopiadores', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $zonaSur->update(['estado' => 'INACTIVO']);
    fixture_proveedor($zonaNorte);
    fixture_proveedor($zonaNorte);
    fixture_acopiador($zonaNorte);

    $datos = (new EloquentZonasListadoQuery)->obtener(ezq_filtroVacio());

    expect($datos->kpis['activas'])->toBe(1);
    expect($datos->kpis['inactivas'])->toBe(1);
    expect($datos->kpis['proveedoresAsignados'])->toBe(2);
    expect($datos->kpis['acopiadoresAsignados'])->toBe(1);

    $fila = collect($datos->filas)->firstWhere('nombre', 'Norte');
    expect($fila['proveedores'])->toBe(2);
    expect($fila['acopiadores'])->toBe(1);
});

it('filtra por nombre y por estado', function () {
    fixture_zona('Zona Norte');
    $sur = fixture_zona('Zona Sur');
    $sur->update(['estado' => 'INACTIVO']);

    $porBusqueda = ZonasFiltro::resolver('Norte', null);
    expect((new EloquentZonasListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);

    $porEstado = ZonasFiltro::resolver(null, 'INACTIVO');
    expect((new EloquentZonasListadoQuery)->obtener($porEstado)->filas)->toHaveCount(1);
});
