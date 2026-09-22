<?php

use App\Application\Ruta\RegistrarRuta;
use App\Application\Zona\EliminarZona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('elimina una zona sin dependencias', function () {
    $zona = fixture_zona('Zona vacía');

    app(EliminarZona::class)->execute($zona->id);

    $this->assertDatabaseMissing('zonas', ['id' => $zona->id]);
});

it('rechaza eliminar una zona con rutas y explica el motivo', function () {
    $zona = fixture_zona('Collana 1');
    app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');

    expect(fn () => app(EliminarZona::class)->execute($zona->id))
        ->toThrow(DomainException::class, 'No se puede eliminar la zona Collana 1 porque tiene 1 ruta(s)');

    $this->assertDatabaseHas('zonas', ['id' => $zona->id]);
});

it('rechaza eliminar una zona con proveedores asignados', function () {
    $zona = fixture_zona('Collana 2');
    fixture_proveedor($zona);

    expect(fn () => app(EliminarZona::class)->execute($zona->id))
        ->toThrow(DomainException::class, 'proveedor(es) asignado(s)');

    $this->assertDatabaseHas('zonas', ['id' => $zona->id]);
});

it('rechaza eliminar una zona que solo conserva historial de proveedores ya reubicados', function () {
    $zona = fixture_zona('Zona con historial');
    $otraZona = fixture_zona('Otra zona');
    $proveedor = fixture_proveedor($otraZona);
    DB::table('proveedor_zona_historial')->insert([
        'proveedor_id' => $proveedor->id,
        'zona_id' => $zona->id,
        'fecha_inicio' => '2026-01-01',
        'fecha_fin' => '2026-06-01',
    ]);

    expect(fn () => app(EliminarZona::class)->execute($zona->id))
        ->toThrow(DomainException::class, '1 registro(s) en el historial de proveedores');
});

it('rechaza eliminar una zona inexistente', function () {
    app(EliminarZona::class)->execute(9999);
})->throws(DomainException::class, 'La zona indicada no existe.');
