<?php

use App\Application\Pago\EstadoAsistenciaActual;
use App\Domain\Shared\Enums\CondicionAsistencia;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Regla global de operación: sin asistencia válida ese día el personal
 * consulta (GET) pero no opera; el servidor la aplica aunque se llame a la API
 * directamente, y para lo hecho sin conexión evalúa el día del EVENTO.
 */
function cuerpoEntrega($proveedor, array $extra = []): array
{
    return ['metodo_identificacion' => 'QR', 'identificador' => $proveedor->qr_token, 'litros' => 20] + $extra;
}

function fechaNegocio(string $modificador = 'today'): string
{
    return now(config('app.zona_horaria_negocio'))->modify($modificador)->format('Y-m-d');
}

it('CASO 1: acopiador sin asistencia consulta pero no registra entregas', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($acopiador->user, 'sanctum')->getJson(route('api.acopiador.proveedores.index'))->assertOk();
    $this->actingAs($acopiador->user, 'sanctum')->getJson(route('api.acopiador.entregas.index'))->assertOk();

    $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.acopiador.entregas.store'), cuerpoEntrega($proveedor))
        ->assertForbidden()
        ->assertJsonPath('code', 'ASISTENCIA_REQUERIDA')
        ->assertJsonPath('message', 'Debes registrar tu asistencia antes de realizar operaciones.')
        ->assertJsonPath('asistencia.estado', 'NO_REGISTRADO');

    expect(EntregaModel::count())->toBe(0);
});

it('CASO 2: acopiador con jornada PRESENTE hoy registra la entrega', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    fixture_presente_hoy($acopiador->user);

    $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.acopiador.entregas.store'), cuerpoEntrega($proveedor))->assertCreated();
});

it('una jornada FALTA no habilita operaciones', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    $trabajador = fixture_presente_hoy($acopiador->user);
    JornadaPersonalModel::where('trabajador_id', $trabajador->id)->update(['estado' => 'FALTA', 'origen' => 'AUTOMATICO', 'hora_marcacion' => null]);

    $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.acopiador.entregas.store'), cuerpoEntrega($proveedor))
        ->assertForbidden()
        ->assertJsonPath('asistencia.estado', 'FALTA');
});

it('CASO 3: calidad sin asistencia ve su historial pero no selecciona muestras', function () {
    $zona = fixture_zona();
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 40.0);
    $analista = fixture_usuario('CALIDAD');

    $this->actingAs($analista, 'sanctum')->getJson(route('api.calidad.analisis-calidad.index', ['tab' => 'historial']))->assertOk();
    $this->actingAs($analista, 'sanctum')->postJson(route('api.calidad.muestreos.store'), [
        'entrega_id' => $entrega->id,
        'metodo_seleccion' => 'MANUAL',
        'tipo_control' => 'DIRIGIDO',
    ])->assertForbidden()->assertJsonPath('code', 'ASISTENCIA_REQUERIDA');

    $this->assertDatabaseCount('muestreos_calidad', 0);
});

it('CASO 4: planta sin asistencia ve productos pero no inicia producción', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $planta = fixture_usuario('ENCARGADO_PLANTA');
    fixture_stock_leche(100.0, $admin->id);
    $queso = fixture_producto('Queso Fresco 1 kg');
    fixture_receta($queso->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']], $admin->id);

    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.productos.index'))->assertOk();
    // Calcular requerimientos es una consulta: sigue libre.
    $this->actingAs($planta, 'sanctum')->postJson(route('api.planta.produccion.requerimientos'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 1]]])->assertOk();
    $this->actingAs($planta, 'sanctum')->postJson(route('api.planta.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 1]]])
        ->assertForbidden()->assertJsonPath('code', 'ASISTENCIA_REQUERIDA');

    expect(LoteProduccionModel::count())->toBe(0);
});

it('CASO 5: ventas sin asistencia ve sus ventas pero no registra una venta (API y Web)', function () {
    $ventas = fixture_usuario('VENTAS');
    $producto = fixture_producto('Queso Paria');

    $this->actingAs($ventas, 'sanctum')->getJson(route('api.ventas.ventas.index'))->assertOk();
    $this->actingAs($ventas, 'sanctum')->postJson(route('api.ventas.ventas.store'), ['producto_id' => $producto->id, 'cantidad' => 1, 'forma_pago' => 'CONTADO'])
        ->assertForbidden()->assertJsonPath('code', 'ASISTENCIA_REQUERIDA');

    // Web: la misma regla; vuelve al formulario con el mensaje, sin registrar nada.
    $this->actingAs($ventas)->from(route('ventas.ventas.index'))->post(route('ventas.ventas.store'), ['producto_id' => $producto->id, 'cantidad' => 1])
        ->assertRedirect(route('ventas.ventas.index'))
        ->assertSessionHas('error', 'Registra tu asistencia antes de realizar operaciones.');

    expect(VentaProductoModel::count())->toBe(0);
});

it('CASO 6: el administrador opera sin jornada (no marca asistencia)', function () {
    // La asistencia se registra antes del cierre de las 07:00 (VentanaAsistencia).
    $this->travelTo(now(config('app.zona_horaria_negocio'))->setTime(6, 30));
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = fixture_acopiador(fixture_zona());
    $trabajador = fixture_presente_hoy($acopiador->user);
    JornadaPersonalModel::query()->delete();

    expect(app(EstadoAsistenciaActual::class)->paraUsuario($admin->id)->condicion)->toBe(CondicionAsistencia::NO_REQUERIDA);
    $this->actingAs($admin, 'sanctum')->getJson(route('api.me.asistencia'))->assertOk()->assertJsonPath('data.puede_operar', true);

    // Opera: registra la asistencia de otro trabajador sin tener jornada propia.
    $this->actingAs($admin, 'sanctum')->postJson(route('api.admin.asistencia.qr'), ['qr_token' => $trabajador->qr_token])->assertSuccessful();
});

it('CASO 7: al proveedor no le aplica la regla', function () {
    $proveedor = fixture_proveedor(fixture_zona());

    expect(app(EstadoAsistenciaActual::class)->paraUsuario($proveedor->user_id)->condicion)->toBe(CondicionAsistencia::NO_REQUERIDA);
    $this->actingAs($proveedor->user, 'sanctum')->getJson(route('api.me.asistencia'))
        ->assertOk()
        ->assertJsonPath('data.requiere_asistencia', false)
        ->assertJsonPath('data.puede_operar', true);
});

it('GET /me/asistencia informa el estado del día con su fecha', function () {
    $analista = fixture_usuario('CALIDAD');

    $this->actingAs($analista, 'sanctum')->getJson(route('api.me.asistencia'))
        ->assertOk()
        ->assertJsonPath('data.fecha', fechaNegocio())
        ->assertJsonPath('data.requiere_asistencia', true)
        ->assertJsonPath('data.puede_operar', false);

    fixture_presente_hoy($analista);

    $this->actingAs($analista, 'sanctum')->getJson(route('api.me.asistencia'))
        ->assertJsonPath('data.puede_operar', true)
        ->assertJsonPath('data.estado', 'TIENE_ASISTENCIA');
});

it('CASO 10: la asistencia de ayer no autoriza hoy', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    fixture_presente_hoy($acopiador->user, fechaNegocio('-1 day'));

    $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.acopiador.entregas.store'), cuerpoEntrega($proveedor))
        ->assertForbidden()
        ->assertJsonPath('asistencia.estado', 'SIN_ASISTENCIA');
});

it('CASO 11: un acopio offline hecho con asistencia válida se acepta al sincronizar otro día', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    // Ayer: PRESENTE. Hoy (día de la sincronización): sin jornada todavía.
    fixture_presente_hoy($acopiador->user, fechaNegocio('-1 day'));
    $evento = now(config('app.zona_horaria_negocio'))->modify('-1 day')->setTime(9, 35)->toIso8601String();

    $this->actingAs($acopiador->user, 'sanctum')
        ->postJson(route('api.acopiador.entregas.store'), cuerpoEntrega($proveedor, ['fecha_hora_evento' => $evento]))
        ->assertCreated();
});

it('CASO 12: una operación cuyo evento no tenía asistencia se rechaza al sincronizar', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    // Hoy SÍ tiene asistencia, pero el evento ocurrió ayer, día sin jornada.
    fixture_presente_hoy($acopiador->user);
    $evento = now(config('app.zona_horaria_negocio'))->modify('-1 day')->setTime(9, 35)->toIso8601String();

    $this->actingAs($acopiador->user, 'sanctum')
        ->postJson(route('api.acopiador.entregas.store'), cuerpoEntrega($proveedor, ['fecha_hora_evento' => $evento]))
        ->assertForbidden()
        ->assertJsonPath('code', 'ASISTENCIA_REQUERIDA')
        ->assertJsonPath('asistencia.fecha', fechaNegocio('-1 day'));

    expect(EntregaModel::count())->toBe(0);
});

it('un bloqueo por asistencia no consume la Idempotency-Key: tras registrar la asistencia el reintento opera', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $planta = fixture_usuario('ENCARGADO_PLANTA');
    fixture_stock_leche(100.0, $admin->id);
    $queso = fixture_producto('Queso Fresco 1 kg');
    fixture_receta($queso->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']], $admin->id);
    $cuerpo = ['productos' => [['producto_id' => $queso->id, 'cantidad' => 1]]];

    $this->actingAs($planta, 'sanctum')->postJson(route('api.planta.produccion.store'), $cuerpo, ['Idempotency-Key' => 'k-1'])->assertForbidden();
    fixture_presente_hoy($planta);
    $this->actingAs($planta, 'sanctum')->postJson(route('api.planta.produccion.store'), $cuerpo, ['Idempotency-Key' => 'k-1'])->assertCreated();

    expect(LoteProduccionModel::count())->toBe(1);
});
