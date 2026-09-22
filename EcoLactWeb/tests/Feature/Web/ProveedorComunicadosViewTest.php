<?php

use App\Application\Comunicacion\CrearComunicado;
use App\Application\Comunicacion\ObtenerLecturaComunicados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de comunicados muestra los KPIs y distingue leídos de no leídos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso importante',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.comunicados.index'));

    $response->assertOk();
    $response->assertSee('No leídos');
    $response->assertSee('● No leído');
});

it('abrir un comunicado lo marca automáticamente como leído', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso importante',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    expect(app(ObtenerLecturaComunicados::class)->execute($proveedor->user_id)[$comunicado->id()] ?? false)->toBeFalse();

    $this->actingAs($proveedor->user)->get(route('proveedor.comunicados.show', $comunicado->id()))->assertOk();

    expect(app(ObtenerLecturaComunicados::class)->execute($proveedor->user_id)[$comunicado->id()])->toBeTrue();

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.comunicados.index'));
    $response->assertSee('✓ Leído');
});
