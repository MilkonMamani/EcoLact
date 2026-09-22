<?php

use App\Application\Recorrido\AbrirRecorrido;
use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\QuitarProveedorDeRuta;
use App\Application\Ruta\RegistrarRuta;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use App\Domain\Shared\Enums\EstadoRecorrido;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('abre un recorrido nuevo con todas las paradas pendientes, copiando orden y ubicación de la ruta', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $p1 = fixture_proveedor($zona);
    $p2 = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(
        rutaId: $ruta->id(),
        proveedorId: $p1->id,
        ordenVisita: 1,
        latitud: -12.1,
        longitud: -76.9,
        referenciaUbicacion: 'Junto al puente',
    );
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p2->id, ordenVisita: 2);

    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id, fechaOperativa: new DateTimeImmutable('2026-09-19'));

    expect($recorrido->estado())->toBe(EstadoRecorrido::PENDIENTE);
    $this->assertDatabaseCount('recorrido_detalles', 2);

    $parada1 = RecorridoDetalleModel::where('recorrido_id', $recorrido->id())->where('proveedor_id', $p1->id)->first();
    expect($parada1->estado)->toBe(EstadoParadaRecorrido::PENDIENTE->value);
    expect($parada1->orden_visita)->toBe(1);
    expect((float) $parada1->latitud)->toBe(-12.1);
    expect($parada1->referencia_ubicacion)->toBe('Junto al puente');
});

it('reutiliza el recorrido abierto existente en vez de crear uno duplicado el mismo día', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $fecha = new DateTimeImmutable('2026-09-19');

    $primero = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id, fechaOperativa: $fecha);
    $segundo = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id, fechaOperativa: $fecha);

    expect($segundo->id())->toBe($primero->id());
    $this->assertDatabaseCount('recorridos_acopio', 1);
});

it('un recorrido histórico conserva sus paradas aunque la ruta cambie después', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $p1 = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p1->id, ordenVisita: 1);

    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id, fechaOperativa: new DateTimeImmutable('2026-09-19'));

    // La ruta cambia después: se agrega otro proveedor y se retira el primero.
    $p2 = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p2->id, ordenVisita: 2);
    app(QuitarProveedorDeRuta::class)->execute($p1->id);

    // El recorrido ya creado sigue mostrando su única parada original (p1), no la configuración actual de la ruta.
    $this->assertDatabaseCount('recorrido_detalles', 1);
    $this->assertDatabaseHas('recorrido_detalles', ['recorrido_id' => $recorrido->id(), 'proveedor_id' => $p1->id]);
});

it('no permite abrir un recorrido para una ruta inactiva', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1', estado: 'INACTIVO');
    $acopiador = fixture_acopiador($zona);

    app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
})->throws(DomainException::class, 'No se puede abrir un recorrido para una ruta inactiva.');

it('no permite asignar un recorrido a un acopiador inactivo', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $acopiador->update(['estado' => 'INACTIVO']);

    app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
})->throws(DomainException::class, 'No se puede asignar un recorrido a un acopiador inactivo.');
