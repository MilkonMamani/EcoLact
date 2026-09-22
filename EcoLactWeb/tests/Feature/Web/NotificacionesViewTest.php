<?php

use App\Infrastructure\Persistence\Models\NotificacionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function crear_notificacion_para_vista(int $userId, bool $leido): NotificacionModel
{
    return NotificacionModel::create([
        'user_id' => $userId,
        'tipo' => 'ENTREGA_REGISTRADA',
        'titulo' => 'Entrega registrada',
        'mensaje' => 'Se registró una entrega pendiente de análisis.',
        'referencia_tipo' => 'ENTREGA',
        'referencia_id' => null,
        'leido' => $leido,
        'fecha_lectura' => $leido ? now() : null,
    ]);
}

it('una notificación no leída se distingue visualmente y ofrece marcarla como leída', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    crear_notificacion_para_vista($proveedor->user_id, leido: false);

    $response = $this->actingAs($proveedor->user)->get(route('notificaciones.index'));

    $response->assertOk();
    $response->assertSee('Marcar como leída');
});

it('el estado vacío se muestra cuando no hay notificaciones', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $response = $this->actingAs($proveedor->user)->get(route('notificaciones.index'));

    $response->assertOk();
    $response->assertSee('No tienes notificaciones');
});
