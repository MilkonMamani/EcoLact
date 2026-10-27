<?php

use App\Application\Recorrido\AbrirRecorrido;
use App\Application\Recorrido\MarcarNoEntrego;
use App\Application\Recorrido\RegistrarAcopioDeRecorrido;
use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

/*
 * API móvil del acopiador trabajando sin conexión: la cola del teléfono
 * reenvía la entrega con su Idempotency-Key y la hora real del acopio.
 */

it('un reintento con la misma Idempotency-Key no duplica la entrega', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    $cuerpo = ['metodo_identificacion' => 'QR', 'identificador' => $proveedor->qr_token, 'litros' => 20];

    $primera = $this->actingAs($acopiador->user, 'sanctum')
        ->withHeader('Idempotency-Key', 'local-1730000000000-42')
        ->postJson(route('api.acopiador.entregas.store'), $cuerpo)
        ->assertCreated();

    $segunda = $this->withHeader('Idempotency-Key', 'local-1730000000000-42')
        ->postJson(route('api.acopiador.entregas.store'), $cuerpo)
        ->assertCreated();

    expect($segunda->json('data.id'))->toBe($primera->json('data.id'));
    $this->assertDatabaseCount('entregas', 1);
});

it('conserva la hora real del acopio offline y distingue la hora de llegada', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    $horaAcopio = now()->subHours(2)->startOfMinute();

    $respuesta = $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.acopiador.entregas.store'), [
        'metodo_identificacion' => 'DNI',
        'identificador' => $proveedor->dni,
        'litros' => 18.5,
        'fecha_hora' => $horaAcopio->toIso8601String(),
    ])->assertCreated();

    expect($respuesta->json('data.fecha_hora'))->toBe($horaAcopio->format(DATE_ATOM));
    expect($respuesta->json('data.fecha_registro'))->not->toBe($respuesta->json('data.fecha_hora'));
});

it('acepta la hora del teléfono en UTC sin correrla por la zona horaria', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    $horaAcopio = now()->subMinutes(90)->startOfMinute();

    $respuesta = $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.acopiador.entregas.store'), [
        'metodo_identificacion' => 'DNI',
        'identificador' => $proveedor->dni,
        'litros' => 9,
        'fecha_hora' => $horaAcopio->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
    ])->assertCreated();

    expect((new DateTimeImmutable($respuesta->json('data.fecha_hora')))->getTimestamp())->toBe($horaAcopio->getTimestamp());
});

it('rechaza con 422 una hora de acopio futura o demasiado antigua', function (string $fecha) {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.acopiador.entregas.store'), [
        'metodo_identificacion' => 'DNI',
        'identificador' => $proveedor->dni,
        'litros' => 10,
        'fecha_hora' => $fecha,
    ])->assertStatus(422)->assertJsonValidationErrors('fecha_hora');

    $this->assertDatabaseCount('entregas', 0);
})->with([
    'futura' => fn () => now()->addHour()->toIso8601String(),
    'de hace dos semanas' => fn () => now()->subDays(14)->toIso8601String(),
]);

it('un proveedor de otra zona responde 422 (la cola no lo reintenta en bucle)', function () {
    $acopiador = fixture_acopiador(fixture_zona());
    $ajeno = fixture_proveedor(fixture_zona());

    $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.acopiador.entregas.store'), [
        'metodo_identificacion' => 'DNI',
        'identificador' => $ajeno->dni,
        'litros' => 10,
    ])->assertStatus(422)->assertJsonPath('message', 'El acopiador no puede registrar entregas de un proveedor de otra zona.');
});

it('no devuelve la entrega de otro usuario aunque coincida la clave', function () {
    $zona = fixture_zona();
    $uno = fixture_acopiador($zona);
    $otro = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    $cuerpo = ['metodo_identificacion' => 'DNI', 'identificador' => $proveedor->dni, 'litros' => 12];

    $this->actingAs($uno->user, 'sanctum')->withHeader('Idempotency-Key', 'misma-clave')
        ->postJson(route('api.acopiador.entregas.store'), $cuerpo)->assertCreated();

    $this->actingAs($otro->user, 'sanctum')->withHeader('Idempotency-Key', 'misma-clave')
        ->postJson(route('api.acopiador.entregas.store'), $cuerpo)->assertStatus(422);
});

it('cada entrega indica su control de calidad según el muestreo', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    $sinMuestra = fixture_entrega($proveedor, $acopiador, 10, new DateTimeImmutable('-3 hours'));
    $seleccionada = fixture_entrega($proveedor, $acopiador, 20, new DateTimeImmutable('-2 hours'));
    $analizada = fixture_entrega($proveedor, $acopiador, 30, new DateTimeImmutable('-1 hours'));
    $analista = fixture_usuario('CALIDAD');
    fixture_muestra($seleccionada, $analista->id);
    fixture_muestra($analizada, $analista->id)->update(['estado' => 'ANALIZADA']);
    fixture_analisis_confirmado($analizada, 'OBSERVADA', $analista->id);

    $filas = collect($this->actingAs($acopiador->user, 'sanctum')
        ->getJson(route('api.acopiador.entregas.index'))
        ->assertOk()
        ->json('data.filas'))->keyBy('id');

    expect($filas[$sinMuestra->id]['control_calidad'])->toBe('SIN_MUESTREO');
    expect($filas[$seleccionada->id]['control_calidad'])->toBe('SELECCIONADA');
    expect($filas[$analizada->id]['control_calidad'])->toBe('OBSERVADA');
    expect($filas[$sinMuestra->id]['proveedor_nombre'])->toBe('Proveedor Test');
    expect($filas[$sinMuestra->id]['zona_nombre'])->toBe($zona->nombre);

    $this->getJson(route('api.acopiador.seguimiento-calidad.show', $sinMuestra->id))
        ->assertOk()
        ->assertJsonPath('data.control_calidad', 'SIN_MUESTREO')
        ->assertJsonPath('data.analisis', null);
});

it('lista los proveedores de la zona con su QR para identificarlos sin conexión', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    fixture_proveedor(fixture_zona());

    $this->actingAs($acopiador->user, 'sanctum')->getJson(route('api.acopiador.proveedores.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data.filas')
        ->assertJsonPath('data.filas.0.qr_token', $proveedor->qr_token)
        ->assertJsonPath('data.filas.0.zona_nombre', $zona->nombre);
});

it('el dashboard trae nombre y zona del acopiador para la cabecera', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $this->actingAs($acopiador->user, 'sanctum')->getJson(route('api.acopiador.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.acopiador.nombres', 'Acopiador')
        ->assertJsonPath('data.acopiador.zona_nombre', $zona->nombre);
});

it('"no entregó" es idempotente y no pisa una parada ya recolectada', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 1');
    $acopiador = fixture_acopiador($zona);
    $p1 = fixture_proveedor($zona);
    $p2 = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p1->id, ordenVisita: 1);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $p2->id, ordenVisita: 2);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $acopiador->id);
    $paradas = RecorridoDetalleModel::where('recorrido_id', $recorrido->id())->orderBy('orden_visita')->get();

    $url = fn ($parada) => route('api.acopiador.recorridos.paradas.no-entrego', [$recorrido->id(), $parada->id]);
    $this->actingAs($acopiador->user, 'sanctum')->postJson($url($paradas[0]), ['motivo' => 'Sin leche'])->assertOk();
    $this->postJson($url($paradas[0]), ['motivo' => 'Sin leche'])->assertOk()->assertJsonPath('data.parada.estado', 'NO_ENTREGO');

    app(RegistrarAcopioDeRecorrido::class)->execute($paradas[1]->id, $acopiador->user_id, 15.0, 'tok-parada-2');
    $this->postJson($url($paradas[1]), ['motivo' => 'Error'])->assertStatus(422);

    expect(app(MarcarNoEntrego::class)->execute($paradas[0]->id, $acopiador->user_id, 'Sin leche')->estado())
        ->toBe(EstadoParadaRecorrido::NO_ENTREGO);
});

it('mi-asistencia devuelve solo el QR y las jornadas del propio trabajador', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    // Esta prueba parte de un usuario SIN ficha de trabajador.
    JornadaPersonalModel::query()->delete();
    TrabajadorModel::where('user_id', $acopiador->user_id)->delete();

    $this->actingAs($acopiador->user, 'sanctum')->getJson(route('api.acopiador.mi-asistencia'))
        ->assertOk()
        ->assertJsonPath('data.registrado', false)
        ->assertJsonPath('data.qr_token', null);

    TrabajadorModel::create([
        'user_id' => $acopiador->user_id,
        'dni' => $acopiador->dni,
        'nombres' => 'Acopiador',
        'apellidos' => 'Test',
        'estado' => 'ACTIVO',
        'fecha_ingreso' => now()->subMonth(),
        'qr_token' => 'qr-trabajador-1',
    ]);

    $this->getJson(route('api.acopiador.mi-asistencia'))
        ->assertOk()
        ->assertJsonPath('data.registrado', true)
        ->assertJsonPath('data.qr_token', 'qr-trabajador-1')
        ->assertJsonPath('data.hora_cierre', '07:00');
});
