<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Application\Ruta\ReordenarRutaDetalles;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function crearRutaConProveedoresOrdenados(int $cantidad): array
{
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta reordenable');

    $detalles = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $proveedor = fixture_proveedor($zona);
        $detalles[] = app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id);
    }

    return [$ruta, $detalles];
}

it('invierte el orden de dos proveedores sin violar el índice único (ruta_id, orden_visita)', function () {
    [$ruta, $detalles] = crearRutaConProveedoresOrdenados(2);
    [$primero, $segundo] = $detalles;

    app(ReordenarRutaDetalles::class)->execute($ruta->id(), [$segundo->id(), $primero->id()]);

    expect(RutaDetalleModel::find($primero->id())->orden_visita)->toBe(2);
    expect(RutaDetalleModel::find($segundo->id())->orden_visita)->toBe(1);
});

it('reordena una lista completa de proveedores', function () {
    [$ruta, $detalles] = crearRutaConProveedoresOrdenados(4);
    [$d1, $d2, $d3, $d4] = $detalles;

    app(ReordenarRutaDetalles::class)->execute($ruta->id(), [$d4->id(), $d1->id(), $d3->id(), $d2->id()]);

    expect(RutaDetalleModel::find($d4->id())->orden_visita)->toBe(1);
    expect(RutaDetalleModel::find($d1->id())->orden_visita)->toBe(2);
    expect(RutaDetalleModel::find($d3->id())->orden_visita)->toBe(3);
    expect(RutaDetalleModel::find($d2->id())->orden_visita)->toBe(4);
});

it('rechaza reordenar con una lista que omite o repite proveedores vigentes', function () {
    [$ruta, $detalles] = crearRutaConProveedoresOrdenados(2);
    [$primero] = $detalles;

    app(ReordenarRutaDetalles::class)->execute($ruta->id(), [$primero->id(), $primero->id()]);
})->throws(DomainException::class);
