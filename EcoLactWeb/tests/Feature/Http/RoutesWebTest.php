<?php

use App\Infrastructure\Persistence\Models\NotificacionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Etapa 10.2 — Tests de routing. NO invocan una vista Blade real (aún no
 * existen, Etapa 10.3): las verificaciones de "existencia"/verbo usan la
 * RouteCollection directamente (Route::has(), ->methods()), y las
 * verificaciones HTTP usan siempre una acción que responde con
 * RedirectResponse (nunca View), o una petición que es rechazada por
 * middleware ANTES de llegar al Controller.
 */
function metodos_de_ruta(string $nombre): array
{
    return RouteFacade::getRoutes()->getByName($nombre)->methods();
}

// 1-3: auth / active / role en rutas admin ------------------------------

it('1. las rutas admin exigen autenticación (auth)', function () {
    $response = $this->getJson(route('admin.zonas.index'));

    $response->assertStatus(401);
});

it('2. las rutas admin exigen cuenta activa (active)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $admin->update(['estado' => 'INACTIVO']);

    $response = $this->actingAs($admin)->get(route('admin.zonas.index'));

    $response->assertStatus(403);
});

it('3. las rutas exclusivas de ADMINISTRADOR restringen el rol (role:ADMINISTRADOR)', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $response = $this->actingAs($proveedor->user)->get(route('admin.zonas.index'));

    $response->assertStatus(403);
});

// 4-7: proveedores --------------------------------------------------------

it('4. admin.proveedores.index existe', fn () => expect(RouteFacade::has('admin.proveedores.index'))->toBeTrue());
it('5. admin.proveedores.store existe', fn () => expect(RouteFacade::has('admin.proveedores.store'))->toBeTrue());
it('6. admin.proveedores.update existe', fn () => expect(RouteFacade::has('admin.proveedores.update'))->toBeTrue());
it('7. admin.proveedores.desactivar existe', fn () => expect(RouteFacade::has('admin.proveedores.desactivar'))->toBeTrue());

// 8-10: acopiadores ---------------------------------------------------------

it('8. admin.acopiadores.index existe', fn () => expect(RouteFacade::has('admin.acopiadores.index'))->toBeTrue());
it('9. admin.acopiadores.desactivar existe', fn () => expect(RouteFacade::has('admin.acopiadores.desactivar'))->toBeTrue());
it('10. admin.acopiadores.reactivar existe', fn () => expect(RouteFacade::has('admin.acopiadores.reactivar'))->toBeTrue());

// 11: zonas -----------------------------------------------------------------

it('11. admin.zonas.index existe', fn () => expect(RouteFacade::has('admin.zonas.index'))->toBeTrue());

// 12-13: entregas -------------------------------------------------------

it('12. admin.entregas.index existe', fn () => expect(RouteFacade::has('admin.entregas.index'))->toBeTrue());
it('13. admin.entregas.store existe', fn () => expect(RouteFacade::has('admin.entregas.store'))->toBeTrue());

// 14-15: análisis de calidad ----------------------------------------------

it('14. las rutas de análisis de calidad existen (show, porEntrega, store, finalizar)', function () {
    expect(RouteFacade::has('admin.analisis-calidad.show'))->toBeTrue();
    expect(RouteFacade::has('admin.entregas.analisis-calidad.index'))->toBeTrue();
    expect(RouteFacade::has('admin.analisis-calidad.store'))->toBeTrue();
    expect(RouteFacade::has('admin.analisis-calidad.finalizar'))->toBeTrue();
});

it('15. finalizar análisis NO usa GET', function () {
    $metodos = metodos_de_ruta('admin.analisis-calidad.finalizar');

    expect($metodos)->not->toContain('GET');
    expect($metodos)->toContain('PATCH');
});

// 16-17: parámetros y reglas de calidad ------------------------------------

it('16. las rutas de parámetros de calidad existen', function () {
    expect(RouteFacade::has('admin.parametros-calidad.index'))->toBeTrue();
    expect(RouteFacade::has('admin.parametros-calidad.show'))->toBeTrue();
    expect(RouteFacade::has('admin.parametros-calidad.store'))->toBeTrue();
    expect(RouteFacade::has('admin.parametros-calidad.update'))->toBeTrue();
});

it('17. las rutas de reglas de calidad existen', function () {
    expect(RouteFacade::has('admin.reglas-calidad.index'))->toBeTrue();
    expect(RouteFacade::has('admin.parametros-calidad.reglas-calidad.index'))->toBeTrue();
    expect(RouteFacade::has('admin.reglas-calidad.store'))->toBeTrue();
    expect(RouteFacade::has('admin.reglas-calidad.update'))->toBeTrue();
});

// 18-19: precios de leche y reglas de pago ---------------------------------

it('18. el precio de leche ya no tiene rutas propias: se configura en Configuración de pagos', function () {
    expect(RouteFacade::has('admin.precios-leche.index'))->toBeFalse();
    expect(RouteFacade::has('admin.precios-leche.store'))->toBeFalse();
    expect(RouteFacade::has('admin.precios-leche.update'))->toBeFalse();
});

it('19. las reglas de pago por calidad ya no tienen rutas propias', function () {
    expect(RouteFacade::has('admin.reglas-pago.index'))->toBeFalse();
    expect(RouteFacade::has('admin.reglas-pago.store'))->toBeFalse();
    expect(RouteFacade::has('admin.reglas-pago.update'))->toBeFalse();
});

// 20-23: configuración de pagos, liquidaciones (solo la hoja imprimible) y pagos ----------

it('20. las rutas de configuración de pagos existen y las de liquidaciones ya no', function () {
    expect(RouteFacade::has('admin.configuracion-pagos.index'))->toBeTrue();
    expect(RouteFacade::has('admin.configuracion-pagos.store'))->toBeTrue();
    expect(RouteFacade::has('admin.configuracion-pagos.update'))->toBeTrue();
    expect(RouteFacade::has('admin.liquidaciones.imprimir'))->toBeTrue();
    expect(RouteFacade::has('admin.liquidaciones.index'))->toBeFalse();
    expect(RouteFacade::has('admin.liquidaciones.show'))->toBeFalse();
    expect(RouteFacade::has('admin.liquidaciones.generar'))->toBeFalse();
});

it('21. guardar y cambiar una configuración de pago NO usan GET', function () {
    expect(metodos_de_ruta('admin.configuracion-pagos.store'))->not->toContain('GET')->toContain('POST');
    expect(metodos_de_ruta('admin.configuracion-pagos.update'))->not->toContain('GET');
});

it('22. las rutas de pagos existen y no hay forma de crear un pago a mano', function () {
    expect(RouteFacade::has('admin.pagos.index'))->toBeTrue();
    expect(RouteFacade::has('admin.pagos.pagar'))->toBeTrue();
    expect(RouteFacade::has('admin.pagos.comprobante'))->toBeTrue();
    expect(RouteFacade::has('admin.pagos.generar'))->toBeTrue();
    expect(RouteFacade::has('admin.pagos.store'))->toBeFalse();
    expect(RouteFacade::has('admin.pagos.update'))->toBeFalse();
});

it('23. registrar pago usa POST', function () {
    expect(metodos_de_ruta('admin.pagos.pagar'))->toContain('POST')->not->toContain('GET');
});

// 24-25: comunicados y producción ------------------------------------------

it('24. las rutas de comunicados existen', function () {
    expect(RouteFacade::has('admin.comunicados.index'))->toBeTrue();
    expect(RouteFacade::has('admin.comunicados.show'))->toBeTrue();
    expect(RouteFacade::has('admin.comunicados.store'))->toBeTrue();
});

it('25. las rutas de producción existen', function () {
    expect(RouteFacade::has('admin.produccion.index'))->toBeTrue();
    expect(RouteFacade::has('admin.produccion.show'))->toBeTrue();
    expect(RouteFacade::has('admin.produccion.store'))->toBeTrue();
    expect(RouteFacade::has('admin.produccion.create'))->toBeTrue();
    expect(RouteFacade::has('admin.produccion.requerimientos'))->toBeTrue();
    expect(RouteFacade::has('admin.produccion.finalizar'))->toBeTrue();
});

// 26-27: notificaciones -----------------------------------------------------

it('26. notificaciones están detrás de auth + active', function () {
    $this->getJson(route('notificaciones.index'))->assertStatus(401);

    $proveedor = fixture_proveedor(fixture_zona());
    $proveedor->user->update(['estado' => 'INACTIVO']);
    $this->actingAs($proveedor->user)->get(route('notificaciones.index'))->assertStatus(403);
});

it('27. marcar notificación leída NO usa GET', function () {
    $metodos = metodos_de_ruta('notificaciones.marcar-leida');

    expect($metodos)->not->toContain('GET');
    expect($metodos)->toContain('PATCH');
});

// 28: Route Model Binding ---------------------------------------------------

it('28. el Route Model Binding resuelve un recurso real (notificacion)', function () {
    $proveedor = fixture_proveedor(fixture_zona());
    $notificacion = NotificacionModel::create([
        'user_id' => $proveedor->user_id,
        'tipo' => 'ENTREGA_REGISTRADA',
        'titulo' => 'Entrega registrada',
        'mensaje' => 'Se registró una entrega pendiente de análisis.',
        'referencia_tipo' => 'ENTREGA',
        'referencia_id' => null,
        'leido' => false,
        'fecha_lectura' => null,
    ]);

    $response = $this->actingAs($proveedor->user)
        ->patch(route('notificaciones.marcar-leida', $notificacion));

    $response->assertRedirect();
    $this->assertDatabaseHas('notificaciones', ['id' => $notificacion->id, 'leido' => true]);
});

// 29-30: seguridad ------------------------------------------------------

it('29. un usuario con rol incorrecto obtiene 403 en una ruta representativa (admin.zonas.store)', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $response = $this->actingAs($proveedor->user)->post(route('admin.zonas.store'), [
        'nombre' => 'Zona intrusa',
        'estado' => 'ACTIVO',
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('zonas', ['nombre' => 'Zona intrusa']);
});

it('30. un usuario no autenticado no puede ejecutar una ruta protegida', function () {
    $response = $this->getJson(route('admin.proveedores.index'));

    $response->assertStatus(401);
});
