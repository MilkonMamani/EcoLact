<?php

use App\Http\Controllers\Web\Comunicacion\NotificacionController;
use App\Infrastructure\Persistence\Models\NotificacionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

/**
 * Crea una notificación de prueba directamente (no existe un Use Case
 * "CrearNotificacion" dedicado: las notificaciones nacen como efecto
 * colateral de otros Use Cases, p. ej. RegistrarEntrega/RegistrarPago).
 */
if (! function_exists('fixture_notificacion')) {
    function fixture_notificacion(int $userId): NotificacionModel
    {
        return NotificacionModel::create([
            'user_id' => $userId,
            'tipo' => 'ENTREGA_REGISTRADA',
            'titulo' => 'Entrega registrada',
            'mensaje' => 'Se registró una entrega pendiente de análisis.',
            'referencia_tipo' => 'ENTREGA',
            'referencia_id' => null,
            'leido' => false,
            'fecha_lectura' => null,
        ]);
    }
}

uses(TestCase::class, RefreshDatabase::class);

// Etapa 10.1.2 — Scope de listados.
it('index (PROVEEDOR A) NO recibe notificaciones del PROVEEDOR B', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $notificacionA = fixture_notificacion($proveedorA->user_id);
    $notificacionB = fixture_notificacion($proveedorB->user_id);

    registrar_vistas_stub();
    $this->actingAs($proveedorA->user);

    $view = app(NotificacionController::class)->index(crear_request_autenticado());

    $ids = collect($view->getData()['notificaciones'])->map(fn ($n) => $n->id());
    expect($ids)->toContain($notificacionA->id)->not->toContain($notificacionB->id);
});

it('marcarComoLeida delega en MarcarNotificacionComoLeida (lectura vía Use Case)', function () {
    Route::post('/_test/notificaciones/{notificacion}/leida', [NotificacionController::class, 'marcarComoLeida'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $notificacion = fixture_notificacion($proveedor->user_id);

    $response = $this->actingAs($proveedor->user)->post("/_test/notificaciones/{$notificacion->id}/leida");

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('notificaciones', ['id' => $notificacion->id, 'leido' => true]);
    expect(NotificacionModel::find($notificacion->id)->fecha_lectura)->not->toBeNull();
});

it('marcarComoLeida ignora cualquier user_id suplantado en el body y usa siempre al usuario autenticado (actor seguro)', function () {
    Route::post('/_test/notificaciones/{notificacion}/leida-actor-seguro', [NotificacionController::class, 'marcarComoLeida'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $otroProveedor = fixture_proveedor($zona);
    $notificacion = fixture_notificacion($proveedor->user_id);

    $response = $this->actingAs($proveedor->user)->post(
        "/_test/notificaciones/{$notificacion->id}/leida-actor-seguro",
        // Un cliente malicioso intenta indicar que otro usuario es quien
        // marca la notificación como leída; el Controller nunca lee este
        // campo, siempre usa $request->user()->id.
        ['user_id' => $otroProveedor->user_id]
    );

    $response->assertRedirect();
    $this->assertDatabaseHas('notificaciones', ['id' => $notificacion->id, 'leido' => true]);
});

it('marcarComoLeida rechaza (403) marcar como leída una notificación de otro usuario', function () {
    Route::post('/_test/notificaciones/{notificacion}/leida-403', [NotificacionController::class, 'marcarComoLeida'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $otroProveedor = fixture_proveedor($zona);
    $notificacion = fixture_notificacion($proveedor->user_id);

    $response = $this->actingAs($otroProveedor->user)->post("/_test/notificaciones/{$notificacion->id}/leida-403");

    $response->assertForbidden();
    $this->assertDatabaseHas('notificaciones', ['id' => $notificacion->id, 'leido' => false]);
});
