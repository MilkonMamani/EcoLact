<?php

use App\Application\Comunicacion\CrearComunicado;
use App\Http\Controllers\Web\Comunicacion\ComunicadoController;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// index()/show() en este Controller solo son alcanzables por ADMINISTRADOR
// en producción (role:ADMINISTRADOR en routes/web.php); el recorte por
// userId de ObtenerComunicados se prueba en
// Web\Proveedor\ComunicadoControllerTest, que es el Controller que
// realmente atiende a ese rol.
it('index delega en ComunicadosListadoQuery y expone los KPIs y el listado a la vista', function () {
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
    $this->actingAs($admin);

    $view = app(ComunicadoController::class)->index(Request::create('/comunicados'));

    expect($view->name())->toBe('admin.comunicados.index');
    expect($view->getData()['datos']->kpis['total'])->toBe(1);
    expect($view->getData()['datos']->kpis['publicados'])->toBe(1);
    expect(collect($view->getData()['datos']->filas)->contains(fn (array $fila) => $fila['id'] === $comunicado->id()))->toBeTrue();
});

it('show delega en ObtenerDetalleComunicado y expone el detalle con destinatarios a la vista', function () {
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
    $this->actingAs($admin);

    $view = app(ComunicadoController::class)->show(ComunicadoModel::find($comunicado->id()));

    expect($view->name())->toBe('admin.comunicados.show');
    expect($view->getData()['detalle']->comunicado->id())->toBe($comunicado->id());
    expect($view->getData()['detalle']->destinatarios)->toHaveCount(1);
    expect($view->getData()['detalle']->noLeidos)->toBe(1);
});

it('store ignora un creado_por_user_id suplantado y usa siempre al administrador autenticado (actor seguro)', function () {
    Route::post('/_test/comunicados-actor-seguro', [ComunicadoController::class, 'store']);

    $admin = fixture_usuario('ADMINISTRADOR');
    $otroAdmin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post('/_test/comunicados-actor-seguro', [
        'titulo' => 'Aviso general',
        'contenido' => 'Contenido del aviso',
        'tipo' => 'AVISO',
        'alcance' => 'GENERAL',
        // Un cliente malicioso intenta hacer figurar a otro administrador
        // como quien creó el comunicado.
        'creado_por_user_id' => $otroAdmin->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('comunicados', [
        'titulo' => 'Aviso general',
        'creado_por_user_id' => $admin->id,
    ]);
});

it('store aplica la Policy: un PROVEEDOR no puede crear comunicados (403)', function () {
    Route::post('/_test/comunicados-403', [ComunicadoController::class, 'store']);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $response = $this->actingAs($proveedor->user)->post('/_test/comunicados-403', [
        'titulo' => 'Aviso general',
        'contenido' => 'Contenido del aviso',
        'tipo' => 'AVISO',
        'alcance' => 'GENERAL',
    ]);

    $response->assertForbidden();
});

it('update permite editar un comunicado en BORRADOR', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Borrador original',
        contenido: 'Contenido original',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
        estado: 'BORRADOR',
    );

    $response = $this->actingAs($admin)->patch(route('admin.comunicados.update', $comunicado->id()), [
        'titulo' => 'Borrador corregido',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('comunicados', [
        'id' => $comunicado->id(),
        'titulo' => 'Borrador corregido',
    ]);
});

it('update devuelve error de servidor al intentar editar un comunicado ya PUBLICADO', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Publicado',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
    );

    $this->actingAs($admin)->patch(route('admin.comunicados.update', $comunicado->id()), [
        'titulo' => 'Intento de edición',
    ])->assertServerError();
});

it('publicar transiciona un comunicado de BORRADOR a PUBLICADO', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Borrador a publicar',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
        estado: 'BORRADOR',
    );

    $response = $this->actingAs($admin)->patch(route('admin.comunicados.publicar', $comunicado->id()));

    $response->assertRedirect();
    $this->assertDatabaseHas('comunicados', [
        'id' => $comunicado->id(),
        'estado' => 'PUBLICADO',
    ]);
});
