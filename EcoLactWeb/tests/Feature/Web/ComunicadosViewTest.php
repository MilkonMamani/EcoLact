<?php

use App\Application\Comunicacion\CrearComunicado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla principal de comunicados muestra los KPIs y el listado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    app(CrearComunicado::class)->execute(
        titulo: 'Reunión de proveedores',
        contenido: 'Contenido del aviso',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    $response = $this->actingAs($admin)->get(route('admin.comunicados.index'));

    $response->assertOk();
    $response->assertSee('Publicados');
    $response->assertSee('Reunión de proveedores');
    // Regresión: Zona expone sus datos mediante getters, no propiedades
    // públicas, así que un collect()->pluck() directo en la vista
    // devolvería el <select> de zona vacío sin fallar (mismo bug ya
    // encontrado en el módulo Stock).
    $response->assertSee($zona->nombre);
});

it('el detalle de un comunicado en BORRADOR muestra los botones de Editar y Publicar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso en borrador',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
        estado: 'BORRADOR',
    );

    $response = $this->actingAs($admin)->get(route('admin.comunicados.show', $comunicado->id()));

    $response->assertOk();
    $response->assertSee('Editar');
    $response->assertSee('Publicar');
});

it('el detalle de un comunicado PUBLICADO no muestra el botón de Editar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso publicado',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    $response = $this->actingAs($admin)->get(route('admin.comunicados.show', $comunicado->id()));

    $response->assertOk();
    $response->assertDontSee('Editar comunicado');
});
