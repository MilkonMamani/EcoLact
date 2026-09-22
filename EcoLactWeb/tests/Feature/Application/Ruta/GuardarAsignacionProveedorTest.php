<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\GuardarAsignacionProveedor;
use App\Application\Ruta\QuitarProveedorDeRuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function ordenesDeRuta(int $rutaId): array
{
    return RutaDetalleModel::where('ruta_id', $rutaId)->orderBy('orden_visita')->pluck('orden_visita')->all();
}

it('asigna un proveedor sin ruta a una ruta guardando su ubicación y referencia', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);

    app(GuardarAsignacionProveedor::class)->execute(
        proveedorId: $proveedor->id,
        rutaId: $ruta->id(),
        latitud: -15.8402,
        longitud: -70.0219,
        referenciaUbicacion: 'Frente a la escuela',
    );

    $this->assertDatabaseHas('ruta_detalles', [
        'ruta_id' => $ruta->id(),
        'proveedor_id' => $proveedor->id,
        'orden_visita' => 1,
        'referencia_ubicacion' => 'Frente a la escuela',
    ]);
    expect((float) RutaDetalleModel::where('proveedor_id', $proveedor->id)->value('latitud'))->toBe(-15.8402);
    expect(ProveedorModel::find($proveedor->id)->zona_id)->toBe($zona->id);
});

it('permite asignar sin ubicación: el proveedor queda "sin ubicación" y sigue asignado', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);

    app(GuardarAsignacionProveedor::class)->execute(proveedorId: $proveedor->id, rutaId: $ruta->id());

    $detalle = RutaDetalleModel::where('proveedor_id', $proveedor->id)->first();
    expect($detalle->latitud)->toBeNull()->and($detalle->longitud)->toBeNull();
});

it('editar la ubicación dentro de la misma ruta conserva orden e historial y no duplica la asignación', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $p1 = fixture_proveedor($zona);
    $p2 = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p1->id);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p2->id);

    app(GuardarAsignacionProveedor::class)->execute(
        proveedorId: $p2->id,
        rutaId: $ruta->id(),
        latitud: -15.5,
        longitud: -70.5,
        referenciaUbicacion: 'A 200 metros de la plaza',
    );

    $detalle = RutaDetalleModel::where('proveedor_id', $p2->id)->first();
    expect($detalle->orden_visita)->toBe(2)
        ->and((float) $detalle->latitud)->toBe(-15.5)
        ->and($detalle->referencia_ubicacion)->toBe('A 200 metros de la plaza');
    $this->assertDatabaseCount('ruta_detalles', 2);
    $this->assertDatabaseCount('proveedor_ruta_historial', 2);
});

it('editar la ubicación puede limpiarla', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, latitud: -15.5, longitud: -70.5);

    app(GuardarAsignacionProveedor::class)->execute(proveedorId: $proveedor->id, rutaId: $ruta->id());

    expect(RutaDetalleModel::where('proveedor_id', $proveedor->id)->value('latitud'))->toBeNull();
});

it('al cambiar de ruta traslada al proveedor con su nueva ubicación y compacta el orden de la ruta de origen', function () {
    $zonaA = fixture_zona('Collana 1');
    $zonaB = fixture_zona('Collana 2');
    $rutaA = app(RegistrarRuta::class)->execute(zonaId: $zonaA->id, nombre: 'Ruta 01');
    $rutaB = app(RegistrarRuta::class)->execute(zonaId: $zonaB->id, nombre: 'Ruta 02');
    $p1 = fixture_proveedor($zonaA);
    $p2 = fixture_proveedor($zonaA);
    $p3 = fixture_proveedor($zonaA);
    foreach ([$p1, $p2, $p3] as $proveedor) {
        app(AsignarProveedorARuta::class)->execute(rutaId: $rutaA->id(), proveedorId: $proveedor->id);
    }

    app(GuardarAsignacionProveedor::class)->execute(proveedorId: $p2->id, rutaId: $rutaB->id(), latitud: -15.9, longitud: -70.1);

    expect(ordenesDeRuta($rutaA->id()))->toBe([1, 2]);
    expect(ordenesDeRuta($rutaB->id()))->toBe([1]);
    expect(RutaDetalleModel::where('proveedor_id', $p3->id)->value('orden_visita'))->toBe(2);
    expect((float) RutaDetalleModel::where('proveedor_id', $p2->id)->value('latitud'))->toBe(-15.9);
    expect(ProveedorModel::find($p2->id)->zona_id)->toBe($zonaB->id);
});

it('rechaza asignar a una ruta inactiva', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01', estado: 'INACTIVO');
    $proveedor = fixture_proveedor($zona);

    app(GuardarAsignacionProveedor::class)->execute(proveedorId: $proveedor->id, rutaId: $ruta->id());
})->throws(DomainException::class, 'No se puede asignar proveedores a una ruta inactiva.');

it('rechaza una ruta inexistente', function () {
    $proveedor = fixture_proveedor(fixture_zona());

    app(GuardarAsignacionProveedor::class)->execute(proveedorId: $proveedor->id, rutaId: 9999);
})->throws(DomainException::class, 'La ruta indicada no existe.');

it('al quitar a un proveedor los órdenes restantes se compactan sin huecos y el proveedor sigue existiendo', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    [$ana, $pedro, $maria, $jose] = [fixture_proveedor($zona), fixture_proveedor($zona), fixture_proveedor($zona), fixture_proveedor($zona)];
    foreach ([$ana, $pedro, $maria, $jose] as $proveedor) {
        app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id);
    }

    app(QuitarProveedorDeRuta::class)->execute($pedro->id);

    expect(ordenesDeRuta($ruta->id()))->toBe([1, 2, 3]);
    expect(RutaDetalleModel::where('proveedor_id', $maria->id)->value('orden_visita'))->toBe(2);
    expect(RutaDetalleModel::where('proveedor_id', $jose->id)->value('orden_visita'))->toBe(3);
    $this->assertDatabaseHas('proveedores', ['id' => $pedro->id]);
});

it('un orden explícito mayor al siguiente disponible se compacta para no dejar huecos', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $p1 = fixture_proveedor($zona);
    $p2 = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p1->id);

    $detalle = app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p2->id, ordenVisita: 7);

    expect(ordenesDeRuta($ruta->id()))->toBe([1, 2]);
    expect($detalle->ordenVisita())->toBe(2);
});
