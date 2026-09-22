<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\QuitarProveedorDeRuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('quita a un proveedor de su ruta vigente y limpia su zona', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id);

    app(QuitarProveedorDeRuta::class)->execute($proveedor->id, motivo: 'Proveedor dejó de operar');

    $this->assertDatabaseMissing('ruta_detalles', ['proveedor_id' => $proveedor->id]);
    $this->assertDatabaseHas('proveedor_ruta_historial', ['proveedor_id' => $proveedor->id, 'ruta_id' => $ruta->id()]);
    $this->assertDatabaseMissing('proveedor_ruta_historial', ['proveedor_id' => $proveedor->id, 'fecha_fin' => null]);
    expect(ProveedorModel::find($proveedor->id)->zona_id)->toBeNull();
});

it('permite reingresar a una ruta (o a otra) a un proveedor previamente retirado', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, ordenVisita: 1);
    app(QuitarProveedorDeRuta::class)->execute($proveedor->id);

    $detalle = app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $proveedor->id, ordenVisita: 1);

    expect($detalle->ordenVisita())->toBe(1);
    $this->assertDatabaseHas('ruta_detalles', ['ruta_id' => $ruta->id(), 'proveedor_id' => $proveedor->id, 'orden_visita' => 1]);
});

it('rechaza quitar a un proveedor que no tiene asignación vigente', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    app(QuitarProveedorDeRuta::class)->execute($proveedor->id);
})->throws(DomainException::class, 'El proveedor no tiene una asignación vigente en ninguna ruta.');
