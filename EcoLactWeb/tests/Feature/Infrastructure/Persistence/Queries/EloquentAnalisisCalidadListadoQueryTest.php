<?php

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Infrastructure\Persistence\Queries\EloquentAnalisisCalidadListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function filtroVacio(): AnalisisCalidadFiltro
{
    return AnalisisCalidadFiltro::resolver(
        busqueda: null,
        zonaId: null,
        resultado: null,
        estado: null,
        fecha: null,
        desde: null,
        hasta: null,
    );
}

it('calcula los KPIs y arma las filas con proveedor, zona y confirmado por', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $proveedorNorte = fixture_proveedor($zonaNorte, '11112222');
    $proveedorSur = fixture_proveedor($zonaSur, '33334444');
    $acopiadorNorte = fixture_acopiador($zonaNorte);
    $acopiadorSur = fixture_acopiador($zonaSur);
    $admin = fixture_usuario('ADMINISTRADOR');
    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');

    $entregaAceptada = fixture_entrega($proveedorNorte, $acopiadorNorte, 30.0);
    $entregaObservada = fixture_entrega($proveedorSur, $acopiadorSur, 20.0);

    $aceptado = app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregaAceptada->id, valores: ['GRASA' => 4.0]);
    app(FinalizarAnalisisCalidad::class)->execute($aceptado->id(), $admin->id);

    $observado = app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregaObservada->id, valores: ['GRASA' => 7.0]);
    app(FinalizarAnalisisCalidad::class)->execute($observado->id(), $admin->id);

    $datos = (new EloquentAnalisisCalidadListadoQuery)->obtener(filtroVacio());

    expect($datos->kpis['total'])->toBe(2);
    expect($datos->kpis['aceptadas'])->toBe(1);
    expect($datos->kpis['observadas'])->toBe(1);
    expect($datos->kpis['rechazadas'])->toBe(0);
    expect($datos->filas)->toHaveCount(2);
    expect($datos->zonas)->toHaveCount(2);

    $filaAceptada = collect($datos->filas)->firstWhere('entregaId', $entregaAceptada->id);
    expect($filaAceptada['proveedorDni'])->toBe('11112222');
    expect($filaAceptada['zonaNombre'])->toBe('Norte');
    expect($filaAceptada['confirmadoPor'])->toBe($admin->username);
    expect($filaAceptada['resultado'])->toBe('ACEPTADA');
});

it('filtra por zona, resultado y búsqueda de proveedor', function () {
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $proveedorNorte = fixture_proveedor($zonaNorte, '55556666');
    $proveedorSur = fixture_proveedor($zonaSur, '77778888');
    $acopiadorNorte = fixture_acopiador($zonaNorte);
    $acopiadorSur = fixture_acopiador($zonaSur);
    $admin = fixture_usuario('ADMINISTRADOR');
    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');

    $entregaNorte = fixture_entrega($proveedorNorte, $acopiadorNorte, 30.0);
    $entregaSur = fixture_entrega($proveedorSur, $acopiadorSur, 20.0);

    $analisisNorte = app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregaNorte->id, valores: ['GRASA' => 4.0]);
    app(FinalizarAnalisisCalidad::class)->execute($analisisNorte->id(), $admin->id);
    app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregaSur->id, valores: ['GRASA' => 4.0]);

    $porZona = AnalisisCalidadFiltro::resolver(null, $zonaNorte->id, null, null, null, null, null);
    $filasPorZona = (new EloquentAnalisisCalidadListadoQuery)->obtener($porZona)->filas;
    expect($filasPorZona)->toHaveCount(1);
    expect($filasPorZona[0]['zonaNombre'])->toBe('Norte');

    $porEstado = AnalisisCalidadFiltro::resolver(null, null, null, 'BORRADOR', null, null, null);
    $filasPorEstado = (new EloquentAnalisisCalidadListadoQuery)->obtener($porEstado)->filas;
    expect($filasPorEstado)->toHaveCount(1);
    expect($filasPorEstado[0]['zonaNombre'])->toBe('Sur');

    $porBusqueda = AnalisisCalidadFiltro::resolver('55556666', null, null, null, null, null, null);
    $filasPorBusqueda = (new EloquentAnalisisCalidadListadoQuery)->obtener($porBusqueda)->filas;
    expect($filasPorBusqueda)->toHaveCount(1);
    expect($filasPorBusqueda[0]['proveedorDni'])->toBe('55556666');
});
