<?php

use App\Application\Comunicacion\CrearComunicado;
use App\Http\Controllers\Web\Proveedor\ComunicadoController;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Este Controller es el único que realmente atiende a un PROVEEDOR en
// producción (routes/web.php lo registra bajo role:PROVEEDOR); el índice/show
// de Web\Comunicacion\ComunicadoController es exclusivo de ADMINISTRADOR.
it('index delega en ObtenerComunicados acotando por el usuario autenticado', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso de zona',
        contenido: 'Contenido del aviso',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    registrar_vistas_stub();
    $this->actingAs($proveedor->user);

    $view = app(ComunicadoController::class)->index(crear_request_autenticado());

    expect($view->name())->toBe('proveedor.comunicados.index');
    expect(collect($view->getData()['comunicados'])->contains(fn ($c) => $c->id() === $comunicado->id()))->toBeTrue();
});

it('index (PROVEEDOR B) NO recibe un comunicado dirigido únicamente al PROVEEDOR A', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicadoA = app(CrearComunicado::class)->execute(
        titulo: 'Aviso solo para A',
        contenido: 'Contenido del aviso',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedorA->user_id],
    );

    registrar_vistas_stub();
    $this->actingAs($proveedorB->user);

    $view = app(ComunicadoController::class)->index(crear_request_autenticado());

    $ids = collect($view->getData()['comunicados'])->map(fn ($c) => $c->id());
    expect($ids)->not->toContain($comunicadoA->id());
});

it('show delega en ObtenerComunicado y expone el comunicado a la vista', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso de zona',
        contenido: 'Contenido del aviso',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    registrar_vistas_stub();
    $this->actingAs($proveedor->user);

    $view = app(ComunicadoController::class)->show(ComunicadoModel::find($comunicado->id()));

    expect($view->name())->toBe('proveedor.comunicados.show');
    expect($view->getData()['comunicado']->id())->toBe($comunicado->id());
});
