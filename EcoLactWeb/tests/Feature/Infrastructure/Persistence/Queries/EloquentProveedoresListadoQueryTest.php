<?php

use App\Application\Proveedor\ProveedoresFiltro;
use App\Infrastructure\Persistence\Queries\EloquentProveedoresListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function epq_filtroVacio(): ProveedoresFiltro
{
    return ProveedoresFiltro::resolver(null, null, null, null);
}

it('calcula los KPIs y arma las filas con zona y advertencias', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $activo = fixture_proveedor($zonaNorte, '11112222');
    $inactivo = fixture_proveedor($zonaSur, '33334444');
    $inactivo->update(['estado' => 'INACTIVO']);
    $conSeguimiento = fixture_proveedor($zonaNorte, '55556666');
    $conSeguimiento->update(['advertencias_count' => 2]);

    $datos = (new EloquentProveedoresListadoQuery)->obtener(epq_filtroVacio());

    expect($datos->kpis['activos'])->toBe(2);
    expect($datos->kpis['inactivos'])->toBe(1);
    expect($datos->kpis['zonasConProveedores'])->toBe(2);
    expect($datos->kpis['requierenSeguimiento'])->toBe(1);
    expect($datos->filas)->toHaveCount(3);

    $fila = collect($datos->filas)->firstWhere('dni', '11112222');
    expect($fila['zonaNombre'])->toBe('Norte');
});

it('filtra por estado, zona, seguimiento y búsqueda', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    fixture_proveedor($zonaNorte, '77778888');
    $inactivo = fixture_proveedor($zonaSur, '99990000');
    $inactivo->update(['estado' => 'INACTIVO']);

    $porEstado = ProveedoresFiltro::resolver(null, 'INACTIVO', null, null);
    expect((new EloquentProveedoresListadoQuery)->obtener($porEstado)->filas)->toHaveCount(1);

    $porZona = ProveedoresFiltro::resolver(null, null, $zonaNorte->id, null);
    expect((new EloquentProveedoresListadoQuery)->obtener($porZona)->filas)->toHaveCount(1);

    $porSeguimiento = ProveedoresFiltro::resolver(null, null, null, 'con_seguimiento');
    expect((new EloquentProveedoresListadoQuery)->obtener($porSeguimiento)->filas)->toHaveCount(0);

    $porBusqueda = ProveedoresFiltro::resolver('77778888', null, null, null);
    expect((new EloquentProveedoresListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);
});
