<?php

use App\Application\Acopio\ActualizarEntrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

it('edita una entrega mientras sigue PENDIENTE_ANALISIS', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $actualizada = app(ActualizarEntrega::class)->execute(
        entregaId: $entrega->id,
        motivoModificacion: 'Corrección de litros mal digitados',
        litros: 32.5,
    );

    expect($actualizada->litros())->toBe(32.5);
    expect($actualizada->proveedorId())->toBe($proveedor->id);
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'litros' => 32.5]);
});

it('rechaza editar sin motivo de modificación', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    app(ActualizarEntrega::class)->execute(
        entregaId: $entrega->id,
        motivoModificacion: '   ',
        litros: 32.5,
    );
})->throws(DomainException::class, 'El motivo de modificación es obligatorio.');

it('rechaza editar una entrega que no existe', function () {
    app(ActualizarEntrega::class)->execute(
        entregaId: 999999,
        motivoModificacion: 'x',
    );
})->throws(DomainException::class, 'La entrega indicada no existe.');

it('rechaza editar una entrega que ya no está PENDIENTE_ANALISIS', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $entrega->update(['estado' => 'ANALIZADA']);

    app(ActualizarEntrega::class)->execute(
        entregaId: $entrega->id,
        motivoModificacion: 'x',
        litros: 40.0,
    );
})->throws(DomainException::class, 'Solo se puede editar una entrega mientras está pendiente de análisis.');
