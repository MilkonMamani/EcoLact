<?php

use App\Application\Comunicacion\CrearComunicado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de comunicados muestra solo los dirigidos al analista autenticado', function () {
    $analista = fixture_usuario('CALIDAD');
    $otroAnalista = fixture_usuario('CALIDAD');
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Actualización de parámetros',
        contenido: 'Contenido del aviso',
        tipo: 'INFORMACION',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$analista->id],
    );

    app(CrearComunicado::class)->execute(
        titulo: 'Solo para otro analista',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$otroAnalista->id],
    );

    $response = $this->actingAs($analista)->get(route('calidad.comunicados.index'));

    $response->assertOk();
    $response->assertSee('Actualización de parámetros');
    $response->assertDontSee('Solo para otro analista');

    $this->actingAs($analista)->get(route('calidad.comunicados.show', $comunicado->id()))
        ->assertOk()
        ->assertSee('Contenido del aviso');
});
