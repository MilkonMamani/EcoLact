<?php

use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * RefreshDatabase ya aplicó todas las migraciones (incluida la de backfill)
 * sobre una base de datos vacía, así que ese primer paso fue un no-op. Este
 * test simula el escenario real -proveedores con zona_id ya asignado antes
 * de que existiera el módulo de Rutas- creándolos directamente (como
 * quedarían en una base de datos existente) y volviendo a ejecutar la
 * migración de backfill sobre ese estado, verificando que traslada
 * correctamente esas asignaciones heredadas.
 */
it('crea una ruta principal por zona y traslada a sus proveedores existentes con orden determinista', function () {
    $zona = fixture_zona('Zona con proveedores previos');
    $p1 = fixture_proveedor($zona, '10000001');
    $p2 = fixture_proveedor($zona, '10000002');
    $p3 = fixture_proveedor($zona, '10000003');

    $migracion = require database_path('migrations/2026_09_19_090005_backfill_rutas_desde_proveedores_zona.php');
    $migracion->up();

    $this->assertDatabaseHas('rutas', ['zona_id' => $zona->id, 'nombre' => 'Ruta principal']);

    $ruta = RutaModel::where('zona_id', $zona->id)->first();
    expect($ruta)->not->toBeNull();

    $ordenes = RutaDetalleModel::where('ruta_id', $ruta->id)
        ->orderBy('orden_visita')
        ->pluck('proveedor_id')
        ->all();

    // Orden determinista por antigüedad de registro (proveedores.id ascendente).
    expect($ordenes)->toBe([$p1->id, $p2->id, $p3->id]);

    foreach ([$p1, $p2, $p3] as $proveedor) {
        $this->assertDatabaseHas('proveedor_ruta_historial', [
            'proveedor_id' => $proveedor->id,
            'ruta_id' => $ruta->id,
            'fecha_fin' => null,
        ]);
    }
});

it('no crea ninguna ruta para una zona sin proveedores', function () {
    fixture_zona('Zona vacía');

    $migracion = require database_path('migrations/2026_09_19_090005_backfill_rutas_desde_proveedores_zona.php');
    $migracion->up();

    $this->assertDatabaseCount('rutas', 0);
});
