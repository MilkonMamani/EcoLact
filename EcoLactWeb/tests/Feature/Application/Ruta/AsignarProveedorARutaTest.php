<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('agrega un proveedor a una ruta y sincroniza su zona', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $proveedor = fixture_proveedor($zona);

    $detalle = app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, ordenVisita: 1);

    expect($detalle->ordenVisita())->toBe(1);
    $this->assertDatabaseHas('ruta_detalles', ['ruta_id' => $ruta->id(), 'proveedor_id' => $proveedor->id, 'orden_visita' => 1]);
    $this->assertDatabaseHas('proveedor_ruta_historial', ['proveedor_id' => $proveedor->id, 'ruta_id' => $ruta->id(), 'fecha_fin' => null]);
    expect(ProveedorModel::find($proveedor->id)->zona_id)->toBe($zona->id);
});

it('asigna el siguiente orden disponible cuando no se indica uno', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $p1 = fixture_proveedor($zona);
    $p2 = fixture_proveedor($zona);

    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p1->id);
    $detalle2 = app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p2->id);

    expect($detalle2->ordenVisita())->toBe(2);
});

it('rechaza un orden de visita ya ocupado en la misma ruta', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $p1 = fixture_proveedor($zona);
    $p2 = fixture_proveedor($zona);

    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p1->id, ordenVisita: 1);

    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p2->id, ordenVisita: 1);
})->throws(DomainException::class, 'Ya existe un proveedor en el orden 1 de esta ruta.');

it('rechaza agregar dos veces al mismo proveedor a la misma ruta', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $proveedor = fixture_proveedor($zona);

    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id);
})->throws(DomainException::class, 'El proveedor ya está asignado a esta ruta.');

it('traslada a un proveedor de una ruta a otra conservando el historial completo', function () {
    $zonaA = fixture_zona('Zona Origen');
    $zonaB = fixture_zona('Zona Destino');
    $rutaA = app(RegistrarRuta::class)->execute(zonaId: $zonaA->id, nombre: 'Ruta A');
    $rutaB = app(RegistrarRuta::class)->execute(zonaId: $zonaB->id, nombre: 'Ruta B');
    $proveedor = fixture_proveedor($zonaA);

    app(AsignarProveedorARuta::class)->execute(rutaId: $rutaA->id(), proveedorId: $proveedor->id, motivo: 'Asignación inicial');
    app(AsignarProveedorARuta::class)->execute(rutaId: $rutaB->id(), proveedorId: $proveedor->id, motivo: 'Reubicación');

    // Solo una asignación vigente: la ruta_detalles de origen debe haberse eliminado.
    $this->assertDatabaseMissing('ruta_detalles', ['ruta_id' => $rutaA->id(), 'proveedor_id' => $proveedor->id]);
    $this->assertDatabaseHas('ruta_detalles', ['ruta_id' => $rutaB->id(), 'proveedor_id' => $proveedor->id]);

    // El historial conserva ambos movimientos: el de origen cerrado, el de destino vigente.
    $this->assertDatabaseHas('proveedor_ruta_historial', ['proveedor_id' => $proveedor->id, 'ruta_id' => $rutaA->id()]);
    $this->assertDatabaseMissing('proveedor_ruta_historial', ['proveedor_id' => $proveedor->id, 'ruta_id' => $rutaA->id(), 'fecha_fin' => null]);
    $this->assertDatabaseHas('proveedor_ruta_historial', ['proveedor_id' => $proveedor->id, 'ruta_id' => $rutaB->id(), 'fecha_fin' => null]);
    $this->assertDatabaseCount('proveedor_ruta_historial', 2);

    // La zona del proveedor queda sincronizada con la ruta destino, con su propio historial de zona.
    expect(ProveedorModel::find($proveedor->id)->zona_id)->toBe($zonaB->id);
    $this->assertDatabaseHas('proveedor_zona_historial', ['proveedor_id' => $proveedor->id, 'zona_id' => $zonaB->id, 'fecha_fin' => null]);
});
