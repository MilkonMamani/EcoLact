<?php

use App\Application\Comunicacion\ComunicadosFiltro;
use App\Application\Comunicacion\CrearComunicado;
use App\Infrastructure\Persistence\Queries\EloquentComunicadosListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function ecq_filtroVacio(): ComunicadosFiltro
{
    return ComunicadosFiltro::resolver(null, null, null, null, null, null);
}

it('calcula los KPIs y arma las filas de comunicados', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);

    app(CrearComunicado::class)->execute(
        titulo: 'Aviso publicado',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedorA->user_id, $proveedorB->user_id],
    );

    app(CrearComunicado::class)->execute(
        titulo: 'Borrador pendiente',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedorA->user_id],
        estado: 'BORRADOR',
    );

    $datos = (new EloquentComunicadosListadoQuery)->obtener(ecq_filtroVacio());

    expect($datos->kpis['total'])->toBe(2);
    expect($datos->kpis['publicados'])->toBe(1);
    expect($datos->kpis['borradores'])->toBe(1);
    expect($datos->kpis['destinatariosAlcanzados'])->toBe(3);
    expect($datos->filas)->toHaveCount(2);

    $filaPublicada = collect($datos->filas)->firstWhere('titulo', 'Aviso publicado');
    expect($filaPublicada['destinatarios'])->toBe(2);
    expect($filaPublicada['estado'])->toBe('PUBLICADO');
});

it('filtra por búsqueda de título, tipo y estado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    app(CrearComunicado::class)->execute(
        titulo: 'Capacitación de calidad',
        contenido: 'Contenido',
        tipo: 'CAPACITACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
    );

    $porBusqueda = ComunicadosFiltro::resolver('Capacitación', null, null, null, null, null);
    expect((new EloquentComunicadosListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);

    $porTipo = ComunicadosFiltro::resolver(null, 'CAPACITACION', null, null, null, null);
    expect((new EloquentComunicadosListadoQuery)->obtener($porTipo)->filas)->toHaveCount(1);

    $porEstado = ComunicadosFiltro::resolver(null, null, 'BORRADOR', null, null, null);
    expect((new EloquentComunicadosListadoQuery)->obtener($porEstado)->filas)->toHaveCount(0);
});
