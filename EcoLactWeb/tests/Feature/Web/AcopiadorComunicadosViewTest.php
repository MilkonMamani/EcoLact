<?php

use App\Application\Comunicacion\CrearComunicado;
use App\Application\Comunicacion\ObtenerLecturaComunicados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de comunicados del acopiador muestra los KPIs y distingue leídos de no leídos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    app(CrearComunicado::class)->execute(
        titulo: 'Actualización del horario de recojo',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$acopiador->user_id],
    );

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.comunicados.index'));

    $response->assertOk();
    $response->assertSee('No leídos');
    $response->assertSee('● No leído');
});

it('abrir un comunicado lo marca automáticamente como leído', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Capacitación',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$acopiador->user_id],
    );

    expect(app(ObtenerLecturaComunicados::class)->execute($acopiador->user_id)[$comunicado->id()] ?? false)->toBeFalse();

    $this->actingAs($acopiador->user)->get(route('acopiador.comunicados.show', $comunicado->id()))->assertOk();

    expect(app(ObtenerLecturaComunicados::class)->execute($acopiador->user_id)[$comunicado->id()])->toBeTrue();
});

it('un acopiador no puede ver un comunicado dirigido a otro usuario (403)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $otro = fixture_acopiador($zona);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Solo para otro',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$otro->user_id],
    );

    $this->actingAs($acopiador->user)->get(route('acopiador.comunicados.show', $comunicado->id()))->assertForbidden();
});
