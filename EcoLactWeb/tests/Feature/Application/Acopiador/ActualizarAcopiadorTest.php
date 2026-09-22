<?php

use App\Application\Acopiador\ActualizarAcopiador;
use App\Application\Acopiador\RegistrarAcopiador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('al cambiar de zona, cierra el historial vigente y abre uno nuevo sin perder el anterior', function () {
    fixture_rol('ACOPIADOR');
    $zonaOrigen = fixture_zona('Zona Origen');
    $zonaDestino = fixture_zona('Zona Destino');

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '12121212',
        nombres: 'Carla',
        apellidos: 'Nina',
        telefono: null,
        zonaId: $zonaOrigen->id,
        username: 'carla.nina',
        password: 'Secreto#123',
    );

    $actualizado = app(ActualizarAcopiador::class)->execute(
        acopiadorId: $acopiador->id(),
        nuevaZonaId: $zonaDestino->id,
        motivoCambioZona: 'Reasignación operativa',
    );

    expect($actualizado->zonaId())->toBe($zonaDestino->id);

    // El historial original (zona origen) sigue existiendo, ahora cerrado.
    $this->assertDatabaseHas('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'zona_id' => $zonaOrigen->id,
    ]);
    $this->assertDatabaseMissing('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'zona_id' => $zonaOrigen->id,
        'fecha_fin' => null,
    ]);

    // Se abrió un nuevo historial vigente para la zona destino.
    $this->assertDatabaseHas('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'zona_id' => $zonaDestino->id,
        'fecha_fin' => null,
        'motivo' => 'Reasignación operativa',
    ]);

    $this->assertDatabaseCount('acopiador_zona_historial', 2);
});

it('actualiza nombres, apellidos y teléfono sin afectar el historial de zona ni el estado', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '34343434',
        nombres: 'Hugo',
        apellidos: 'Apaza',
        telefono: '70000001',
        zonaId: $zona->id,
        username: 'hugo.apaza',
        password: 'Secreto#123',
    );

    $actualizado = app(ActualizarAcopiador::class)->execute(
        acopiadorId: $acopiador->id(),
        nombres: 'Hugo Renato',
        telefono: '70000002',
    );

    expect($actualizado->nombres())->toBe('Hugo Renato');
    expect($actualizado->apellidos())->toBe('Apaza');
    expect($actualizado->telefono())->toBe('70000002');
    expect($actualizado->estado())->toBe($acopiador->estado());
    expect($actualizado->zonaId())->toBe($zona->id);

    $this->assertDatabaseCount('acopiador_zona_historial', 1);
});

it('ActualizarAcopiador ya no admite cambiar el estado (eso es DesactivarAcopiador/ReactivarAcopiador)', function () {
    $reflexion = new ReflectionMethod(ActualizarAcopiador::class, 'execute');
    $nombresParametros = array_map(
        fn (ReflectionParameter $parametro): string => $parametro->getName(),
        $reflexion->getParameters(),
    );

    expect($nombresParametros)->not->toContain('estado');
});
