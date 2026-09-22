<?php

use App\Application\Comunicacion\CrearComunicado;
use App\Application\Comunicacion\MarcarComunicadoComoLeido;
use App\Application\Comunicacion\ObtenerLecturaComunicados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('marca un comunicado como leído para su destinatario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    expect(app(ObtenerLecturaComunicados::class)->execute($proveedor->user_id)[$comunicado->id()] ?? false)->toBeFalse();

    app(MarcarComunicadoComoLeido::class)->execute($comunicado->id(), $proveedor->user_id);

    expect(app(ObtenerLecturaComunicados::class)->execute($proveedor->user_id)[$comunicado->id()])->toBeTrue();
});

it('no hace nada si el usuario no es destinatario del comunicado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $destinatario = fixture_proveedor($zona);
    $ajeno = fixture_proveedor($zona);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$destinatario->user_id],
    );

    app(MarcarComunicadoComoLeido::class)->execute($comunicado->id(), $ajeno->user_id);

    expect(app(ObtenerLecturaComunicados::class)->execute($ajeno->user_id))->toBe([]);
});

it('es idempotente: marcar dos veces no falla ni cambia el resultado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    app(MarcarComunicadoComoLeido::class)->execute($comunicado->id(), $proveedor->user_id);
    app(MarcarComunicadoComoLeido::class)->execute($comunicado->id(), $proveedor->user_id);

    expect(app(ObtenerLecturaComunicados::class)->execute($proveedor->user_id)[$comunicado->id()])->toBeTrue();
});
