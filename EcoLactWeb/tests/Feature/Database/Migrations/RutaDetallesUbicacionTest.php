<?php

use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('ruta_detalles tiene columnas de ubicación y se conservan las de proveedores para no perder datos', function () {
    expect(Schema::hasColumns('ruta_detalles', ['latitud', 'longitud', 'referencia_ubicacion']))->toBeTrue();
    expect(Schema::hasColumn('proveedores', 'latitud'))->toBeTrue();
});

it('copia a la asignación vigente las coordenadas que el proveedor ya tenía registradas', function () {
    $zona = fixture_zona();
    $conUbicacion = fixture_proveedor($zona, '10000001');
    $sinUbicacion = fixture_proveedor($zona, '10000002');
    $ruta = DB::table('rutas')->insertGetId(['zona_id' => $zona->id, 'nombre' => 'Ruta 01', 'estado' => 'ACTIVO']);
    foreach ([$conUbicacion, $sinUbicacion] as $indice => $proveedor) {
        DB::table('ruta_detalles')->insert(['ruta_id' => $ruta, 'proveedor_id' => $proveedor->id, 'orden_visita' => $indice + 1]);
    }
    DB::table('proveedores')->where('id', $conUbicacion->id)->update([
        'latitud' => -15.8402,
        'longitud' => -70.0219,
        'referencia_ubicacion' => 'Junto al puente',
    ]);

    $migracion = require database_path('migrations/2026_09_20_100000_alter_ruta_detalles_add_ubicacion.php');
    $migracion->down();
    $migracion->up();

    $detalle = RutaDetalleModel::where('proveedor_id', $conUbicacion->id)->first();
    expect((float) $detalle->latitud)->toBe(-15.8402)
        ->and((float) $detalle->longitud)->toBe(-70.0219)
        ->and($detalle->referencia_ubicacion)->toBe('Junto al puente');
    expect(RutaDetalleModel::where('proveedor_id', $sinUbicacion->id)->value('latitud'))->toBeNull();
});
