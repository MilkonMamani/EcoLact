<?php

use App\Application\Acopiador\DesactivarAcopiador;
use App\Application\Acopiador\ReactivarAcopiador;
use App\Application\Acopiador\RegistrarAcopiador;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\AcopiadorZonaHistorialModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('reactiva un acopiador inactivo asignándolo a la zona indicada y crea un nuevo historial', function () {
    fixture_rol('ACOPIADOR');
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '40404040',
        nombres: 'Ruben',
        apellidos: 'Choquehuanca',
        telefono: null,
        zonaId: $zonaA->id,
        username: 'ruben.choquehuanca',
        password: 'Secreto#123',
    );

    app(DesactivarAcopiador::class)->execute($acopiador->id());

    $reactivado = app(ReactivarAcopiador::class)->execute($acopiador->id(), $zonaB->id);

    expect($reactivado->estado())->toBe(EstadoUsuario::ACTIVO);
    expect($reactivado->zonaId())->toBe($zonaB->id);

    // Periodo original (Zona A) sigue existiendo, cerrado.
    $this->assertDatabaseHas('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'zona_id' => $zonaA->id,
    ]);
    $this->assertDatabaseMissing('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'zona_id' => $zonaA->id,
        'fecha_fin' => null,
    ]);

    // Nuevo periodo vigente para la Zona B.
    $this->assertDatabaseHas('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'zona_id' => $zonaB->id,
        'fecha_fin' => null,
    ]);
    $this->assertDatabaseCount('acopiador_zona_historial', 2);
});

it('al reactivar a la MISMA zona, igualmente crea un nuevo período histórico (no reabre el anterior)', function () {
    fixture_rol('ACOPIADOR');
    $zonaA = fixture_zona('Zona A');

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '50505050',
        nombres: 'Elsa',
        apellidos: 'Yupanqui',
        telefono: null,
        zonaId: $zonaA->id,
        username: 'elsa.yupanqui',
        password: 'Secreto#123',
    );

    app(DesactivarAcopiador::class)->execute($acopiador->id());
    app(ReactivarAcopiador::class)->execute($acopiador->id(), $zonaA->id);

    // Dos filas para la misma zona: el periodo cerrado original y el nuevo
    // periodo vigente, nunca se reutiliza/reabre la fila cerrada.
    $this->assertDatabaseCount('acopiador_zona_historial', 2);

    $historiales = AcopiadorZonaHistorialModel::where('acopiador_id', $acopiador->id())->get();
    expect($historiales)->toHaveCount(2);
    expect($historiales->where('zona_id', $zonaA->id)->count())->toBe(2);
    expect($historiales->whereNull('fecha_fin')->count())->toBe(1);
});

it('exige que la zona de reactivación exista y esté activa', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '60606060',
        nombres: 'Freddy',
        apellidos: 'Laura',
        telefono: null,
        zonaId: $zona->id,
        username: 'freddy.laura',
        password: 'Secreto#123',
    );

    app(DesactivarAcopiador::class)->execute($acopiador->id());

    app(ReactivarAcopiador::class)->execute($acopiador->id(), 999999);
})->throws(DomainException::class, 'La zona indicada no existe o no está activa.');

it('rechaza reactivar un acopiador que ya está activo', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '70707070',
        nombres: 'Nora',
        apellidos: 'Quenta',
        telefono: null,
        zonaId: $zona->id,
        username: 'nora.quenta',
        password: 'Secreto#123',
    );

    app(ReactivarAcopiador::class)->execute($acopiador->id(), $zona->id);
})->throws(DomainException::class, 'El acopiador ya se encuentra activo.');
