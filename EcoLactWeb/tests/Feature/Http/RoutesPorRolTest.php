<?php

use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Http\Controllers\Web\Acopiador\EntregaController as AcopiadorEntregaController;
use App\Http\Controllers\Web\Acopiador\ProveedorController as AcopiadorProveedorController;
use App\Http\Controllers\Web\Analista\EntregaController;
use App\Http\Controllers\Web\Proveedor\AnalisisController as ProveedorAnalisisController;
use App\Http\Controllers\Web\Proveedor\EntregaController as ProveedorEntregaController;
use App\Http\Controllers\Web\Proveedor\QrController as ProveedorQrController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

/*
|--------------------------------------------------------------------------
| Etapa 10.2.1 — Tests de routing por rol
|--------------------------------------------------------------------------
| Igual que en Etapa 10.2: las verificaciones de "existencia"/estructura
| usan la RouteCollection directamente (Route::has()); las de contenido de
| un listado llaman al Controller directamente (registrar_vistas_stub() +
| crear_request_autenticado(), ya usados en 10.1.1/10.1.2) porque las
| vistas Blade reales aún no existen (Etapa 10.3); las de rechazo (401/403)
| usan una petición HTTP real porque el middleware/Policy responde ANTES de
| intentar renderizar cualquier vista.
*/

// ---------------------------------------------------------------------
// ADMIN (1-3)
// ---------------------------------------------------------------------

it('1. ADMIN puede acceder a /admin/proveedores (pasa auth+active+role+Policy)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.proveedores.index'));

    // La vista Blade aún no existe (Etapa 10.3): lo único que interesa
    // aquí es que NO fue rechazado por middleware/Policy (401/403).
    expect($response->status())->not->toBe(401);
    expect($response->status())->not->toBe(403);
});

it('2. ACOPIADOR recibe 403 en una ruta administrativa exclusiva', function () {
    $acopiador = fixture_acopiador(fixture_zona());

    $response = $this->actingAs($acopiador->user)->get(route('admin.zonas.index'));

    $response->assertStatus(403);
});

it('3. PROVEEDOR recibe 403 en una ruta administrativa exclusiva', function () {
    $proveedor = fixture_proveedor(fixture_zona());

    $response = $this->actingAs($proveedor->user)->get(route('admin.zonas.index'));

    $response->assertStatus(403);
});

// ---------------------------------------------------------------------
// ACOPIADOR (4-9)
// ---------------------------------------------------------------------

it('4. acopiador.proveedores.index existe', fn () => expect(RouteFacade::has('acopiador.proveedores.index'))->toBeTrue());
it('5. acopiador.entregas.index existe', fn () => expect(RouteFacade::has('acopiador.entregas.index'))->toBeTrue());

it('6. ACOPIADOR puede registrar entrega vía /acopiador/entregas', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->post(route('acopiador.entregas.store'), [
        'metodo_identificacion' => 'DNI',
        'identificador' => $proveedor->dni,
        'litros' => 25.0,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('entregas', [
        'proveedor_id' => $proveedor->id,
        'acopiador_id' => $acopiador->id,
    ]);
});

it('7. PROVEEDOR no puede acceder al área /acopiador (403)', function () {
    $proveedor = fixture_proveedor(fixture_zona());

    $response = $this->actingAs($proveedor->user)->get(route('acopiador.proveedores.index'));

    $response->assertStatus(403);
});

it('8. ACOPIADOR (Zona A) NO recibe proveedores de Zona B', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);

    registrar_vistas_stub();
    $this->actingAs($acopiadorA->user);

    $view = app(AcopiadorProveedorController::class)->index(crear_request_autenticado());

    $ids = collect($view->getData()['proveedores'])->map(fn ($p) => $p->id());
    expect($ids)->toContain($proveedorA->id)->not->toContain($proveedorB->id);
});

it('9. ACOPIADOR (Zona A) NO recibe entregas de Zona B', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);
    $entregaA = fixture_entrega($proveedorA, $acopiadorA, 20.0);
    $entregaB = fixture_entrega($proveedorB, $acopiadorB, 20.0);

    registrar_vistas_stub();
    $this->actingAs($acopiadorA->user);

    $view = app(AcopiadorEntregaController::class)->index(crear_request_autenticado());

    $ids = collect($view->getData()['entregas'])->map(fn ($e) => $e->id());
    expect($ids)->toContain($entregaA->id)->not->toContain($entregaB->id);
});

// ---------------------------------------------------------------------
// PROVEEDOR (10-18)
// ---------------------------------------------------------------------

it('10. proveedor.perfil existe', fn () => expect(RouteFacade::has('proveedor.perfil'))->toBeTrue());
it('11. proveedor.qr existe', fn () => expect(RouteFacade::has('proveedor.qr'))->toBeTrue());
it('12. proveedor.entregas.index existe', fn () => expect(RouteFacade::has('proveedor.entregas.index'))->toBeTrue());
it('13. proveedor.analisis.index existe', fn () => expect(RouteFacade::has('proveedor.analisis.index'))->toBeTrue());
it('14. proveedor.liquidaciones.index existe', fn () => expect(RouteFacade::has('proveedor.liquidaciones.index'))->toBeTrue());
it('15. proveedor.pagos.index existe', fn () => expect(RouteFacade::has('proveedor.pagos.index'))->toBeTrue());
it('16. proveedor.comunicados.index existe', fn () => expect(RouteFacade::has('proveedor.comunicados.index'))->toBeTrue());

it('17. ACOPIADOR no puede acceder al portal /proveedor (403)', function () {
    $acopiador = fixture_acopiador(fixture_zona());

    $response = $this->actingAs($acopiador->user)->get(route('proveedor.perfil'));

    $response->assertStatus(403);
});

it('18. PROVEEDOR solo recibe sus propias entregas (no las de otro proveedor)', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entregaA = fixture_entrega($proveedorA, $acopiador, 15.0);
    $entregaB = fixture_entrega($proveedorB, $acopiador, 15.0);

    registrar_vistas_stub();
    $this->actingAs($proveedorA->user);

    $view = app(ProveedorEntregaController::class)->index(crear_request_autenticado());

    $ids = collect($view->getData()['entregas'])->map(fn ($e) => $e->id());
    expect($ids)->toContain($entregaA->id)->not->toContain($entregaB->id);
});

// ---------------------------------------------------------------------
// QR (19-20)
// ---------------------------------------------------------------------

it('19. el proveedor obtiene únicamente su propio qr_token', function () {
    $proveedor = fixture_proveedor(fixture_zona());

    registrar_vistas_stub();
    $this->actingAs($proveedor->user);

    $view = app(ProveedorQrController::class)->show(crear_request_autenticado());

    expect($view->getData()['proveedor']->qrToken())->toBe($proveedor->qr_token);
});

it('20. un proveedor no puede obtener el QR de otro (siempre resuelve el propio)', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);

    registrar_vistas_stub();

    $this->actingAs($proveedorA->user);
    $qrA = app(ProveedorQrController::class)->show(crear_request_autenticado())->getData()['proveedor']->qrToken();

    $this->actingAs($proveedorB->user);
    $qrB = app(ProveedorQrController::class)->show(crear_request_autenticado())->getData()['proveedor']->qrToken();

    expect($qrA)->not->toBe($qrB);
    expect($qrA)->toBe($proveedorA->qr_token);
    expect($qrB)->toBe($proveedorB->qr_token);
});

// ---------------------------------------------------------------------
// ANÁLISIS (21-22)
// ---------------------------------------------------------------------

it('21. el listado "mis análisis" del proveedor solo contiene análisis propios', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $entregaA = fixture_entrega($proveedorA, $acopiador, 20.0);
    $analisisA = fixture_analisis_confirmado($entregaA, 'ACEPTADA', $admin->id);

    $entregaB = fixture_entrega($proveedorB, $acopiador, 20.0);
    $analisisB = fixture_analisis_confirmado($entregaB, 'ACEPTADA', $admin->id);

    registrar_vistas_stub();
    $this->actingAs($proveedorA->user);

    $view = app(ProveedorAnalisisController::class)->index(crear_request_autenticado());

    $ids = collect($view->getData()['analisis'])->map(fn ($a) => $a->id());
    expect($ids)->toContain($analisisA->id)->not->toContain($analisisB->id);
});

it('22. el análisis de otro proveedor queda fuera del acceso directo (403)', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $entregaB = fixture_entrega($proveedorB, $acopiador, 20.0);
    $analisisB = fixture_analisis_confirmado($entregaB, 'ACEPTADA', $admin->id);

    $response = $this->actingAs($proveedorA->user)
        ->get(route('proveedor.analisis.show', ['analisis' => $analisisB->id]));

    $response->assertStatus(403);
});

// ---------------------------------------------------------------------
// RUTAS (23-25)
// ---------------------------------------------------------------------

it('23. no queda ninguna ruta bajo /admin sin middleware role:ADMINISTRADOR', function () {
    $rutasAdminSinRestriccion = collect(RouteFacade::getRoutes())
        ->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'admin/'))
        ->reject(fn ($ruta) => in_array('role:ADMINISTRADOR', $ruta->gatherMiddleware(), true))
        ->map(fn ($ruta) => $ruta->uri());

    expect($rutasAdminSinRestriccion->all())->toBe([]);
});

it('24. no hay nombres de ruta duplicados', function () {
    $nombres = collect(RouteFacade::getRoutes())
        ->map(fn ($ruta) => $ruta->getName())
        ->filter();

    expect($nombres->count())->toBe($nombres->unique()->count());
});

it('25. el Route Model Binding sigue funcionando en las rutas nuevas por rol', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 20.0);
    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');
    $analista = fixture_usuario('CALIDAD');

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 3.5],
    );

    $response = $this->actingAs($analista)
        ->patch(route('calidad.analisis-calidad.finalizar', ['analisis' => $analisis->id()]));

    $response->assertRedirect();
    $this->assertDatabaseHas('analisis_calidad', [
        'id' => $analisis->id(),
        'estado' => 'CONFIRMADO',
    ]);
});

it('26. ACOPIADOR ya no puede registrar/finalizar análisis de calidad (403): eso es exclusivo de CALIDAD', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 20.0);

    $response = $this->actingAs($acopiador->user)->post(route('calidad.analisis-calidad.store'), [
        'entrega_id' => $entrega->id,
        'valores' => ['GRASA' => 3.5],
    ]);

    $response->assertStatus(403);
});

it('27. CALIDAD puede ver entregas de cualquier zona (no está acotado a una propia)', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);
    $entregaA = fixture_entrega($proveedorA, $acopiadorA, 20.0);
    $entregaB = fixture_entrega($proveedorB, $acopiadorB, 20.0);
    $analista = fixture_usuario('CALIDAD');

    registrar_vistas_stub();
    $this->actingAs($analista);

    $view = app(EntregaController::class)->index(crear_request_autenticado());

    $ids = collect($view->getData()['datos']->filas)->map(fn (array $fila) => $fila['id']);
    expect($ids)->toContain($entregaA->id)->toContain($entregaB->id);
});

it('28. CALIDAD puede redactar un comunicado dirigido a cualquier proveedor', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $analista = fixture_usuario('CALIDAD');

    $response = $this->actingAs($analista)->post(route('calidad.comunicados.store'), [
        'titulo' => 'Propuesta de capacitación',
        'contenido' => 'Se sugiere una capacitación sobre buenas prácticas de ordeño.',
        'tipo' => 'CAPACITACION',
        'alcance' => 'INDIVIDUAL',
        'destinatarios' => [$proveedor->user_id],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('comunicados', ['titulo' => 'Propuesta de capacitación']);
});
