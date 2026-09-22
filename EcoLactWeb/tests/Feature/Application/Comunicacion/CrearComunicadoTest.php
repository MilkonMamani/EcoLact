<?php

use App\Application\Comunicacion\CrearComunicado;
use App\Infrastructure\Persistence\Models\ComunicadoDestinatarioModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('resuelve automáticamente los destinatarios de una zona cuando el alcance es ZONA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $proveedorNorte = fixture_proveedor($zonaNorte);
    $proveedorSur = fixture_proveedor($zonaSur);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso de zona',
        contenido: 'Contenido del aviso',
        tipo: 'AVISO',
        alcance: 'ZONA',
        creadoPorUserId: $admin->id,
        zonaId: $zonaNorte->id,
    );

    expect(ComunicadoDestinatarioModel::where('comunicado_id', $comunicado->id())->where('user_id', $proveedorNorte->user_id)->exists())->toBeTrue();
    expect(ComunicadoDestinatarioModel::where('comunicado_id', $comunicado->id())->where('user_id', $proveedorSur->user_id)->exists())->toBeFalse();
});

it('resuelve automáticamente todos los proveedores activos cuando el alcance es GENERAL', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedorActivo = fixture_proveedor($zona);
    $proveedorInactivo = fixture_proveedor($zona);
    $proveedorInactivo->update(['estado' => 'INACTIVO']);

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Aviso general',
        contenido: 'Contenido del aviso',
        tipo: 'AVISO',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
    );

    expect(ComunicadoDestinatarioModel::where('comunicado_id', $comunicado->id())->where('user_id', $proveedorActivo->user_id)->exists())->toBeTrue();
    expect(ComunicadoDestinatarioModel::where('comunicado_id', $comunicado->id())->where('user_id', $proveedorInactivo->user_id)->exists())->toBeFalse();
});

it('rechaza un tipo de comunicado que no pertenece al catálogo permitido', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    app(CrearComunicado::class)->execute(
        titulo: 'Aviso inválido',
        contenido: 'Contenido',
        tipo: 'TIPO_INVENTADO',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
    );
})->throws(DomainException::class);

it('rechaza un alcance ZONA sin indicar la zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    app(CrearComunicado::class)->execute(
        titulo: 'Aviso inválido',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'ZONA',
        creadoPorUserId: $admin->id,
    );
})->throws(DomainException::class);

it('permite guardar un comunicado como BORRADOR sin publicarlo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Borrador',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
        estado: 'BORRADOR',
    );

    expect($comunicado->estado())->toBe('BORRADOR');
});
