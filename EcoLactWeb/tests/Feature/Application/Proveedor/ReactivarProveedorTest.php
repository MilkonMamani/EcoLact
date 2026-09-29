<?php

use App\Application\Proveedor\DesactivarProveedor;
use App\Application\Proveedor\ReactivarProveedor;
use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\ProveedorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('reactiva a un proveedor inactivo y reinicia su contador de advertencias', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $proveedor = fixture_proveedor($zona);

    $proveedor->update(['advertencias_count' => 3]);
    app(DesactivarProveedor::class)->execute($proveedor->id);

    $reactivado = app(ReactivarProveedor::class)->execute($proveedor->id, $ruta->id());

    expect($reactivado->estado()->value)->toBe('ACTIVO');
    expect($reactivado->advertenciasCount())->toBe(0);
});

it('no permite reactivar a un proveedor que ya está activo', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $proveedor = fixture_proveedor($zona);

    app(ReactivarProveedor::class)->execute($proveedor->id, $ruta->id());
})->throws(DomainException::class, 'El proveedor ya se encuentra activo.');

it('al reincorporarlo en otra zona lo traslada a la ruta elegida y cierra el historial anterior sin borrarlo', function () {
    $collana = fixture_zona('Collana 1');
    $faon = fixture_zona('Faon');
    $rutaCollana = app(RegistrarRuta::class)->execute(zonaId: $collana->id, nombre: 'Ruta Collana');
    $rutaFaon = app(RegistrarRuta::class)->execute(zonaId: $faon->id, nombre: 'Ruta Faon');
    $proveedor = fixture_proveedor($collana);
    ProveedorZonaHistorialModel::create(['proveedor_id' => $proveedor->id, 'zona_id' => $collana->id, 'fecha_inicio' => '2026-09-01', 'motivo' => 'Registro']);
    app(AsignarProveedorARuta::class)->execute(rutaId: $rutaCollana->id(), proveedorId: $proveedor->id);
    app(DesactivarProveedor::class)->execute($proveedor->id);

    $reactivado = app(ReactivarProveedor::class)->execute($proveedor->id, $rutaFaon->id());

    expect($reactivado->zonaId())->toBe($faon->id);
    expect(RutaDetalleModel::where('proveedor_id', $proveedor->id)->value('ruta_id'))->toBe($rutaFaon->id());

    $tramos = ProveedorZonaHistorialModel::where('proveedor_id', $proveedor->id)->orderBy('id')->get();
    expect($tramos->last()->zona_id)->toBe($faon->id)
        ->and($tramos->last()->fecha_fin)->toBeNull()
        ->and($tramos->last()->motivo)->toBe('Reactivación del proveedor.')
        ->and($tramos->where('zona_id', $collana->id)->whereNotNull('fecha_fin'))->not->toBeEmpty();
});

it('si sigue asignado a la misma ruta conserva su asignación y su punto de recolección', function () {
    $zona = fixture_zona('Collana 1');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, latitud: -15.84, longitud: -70.02);
    app(DesactivarProveedor::class)->execute($proveedor->id);
    $detalleAntes = RutaDetalleModel::where('proveedor_id', $proveedor->id)->sole();

    app(ReactivarProveedor::class)->execute($proveedor->id, $ruta->id());

    $detalleDespues = RutaDetalleModel::where('proveedor_id', $proveedor->id)->sole();
    expect($detalleDespues->id)->toBe($detalleAntes->id)
        ->and((float) $detalleDespues->latitud)->toBe(-15.84);
});

it('rechaza una ruta inactiva y deja al proveedor inactivo', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    RutaModel::whereKey($ruta->id())->update(['estado' => 'INACTIVA']);
    $proveedor = fixture_proveedor($zona);
    $proveedor->update(['estado' => 'INACTIVO']);

    expect(fn () => app(ReactivarProveedor::class)->execute($proveedor->id, $ruta->id()))
        ->toThrow(DomainException::class, 'La ruta indicada no existe o no está activa.');
    expect($proveedor->fresh()->estado)->toBe('INACTIVO');
});
