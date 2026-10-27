<?php

use App\Application\Acopio\CorregirLitrosEntrega;
use App\Application\Recorrido\AbrirRecorrido;
use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * La fecha de una entrega es CUÁNDO OCURRIÓ EL ACOPIO, no cuándo llegó internet:
 * acopio offline 09:35 (Perú) + sincronización 11:00 → entregas.fecha_hora = 09:35,
 * entregas.created_at = 11:00.
 */

const EVENTO_0935 = '2026-10-27T09:35:12-05:00';

beforeEach(function () {
    fixture_personal_con_asistencia_hoy();
    Carbon::setTestNow(Carbon::parse('2026-10-27T11:00:43-05:00'));
    $this->zona = fixture_zona();
    $this->acopiador = fixture_acopiador($this->zona);
    $this->proveedor = fixture_proveedor($this->zona);
});

afterEach(fn () => Carbon::setTestNow());

function postEntregaEvento($test, array $extra = [], ?string $clave = null)
{
    $peticion = $test->actingAs($test->acopiador->user, 'sanctum');
    if ($clave !== null) {
        $peticion = $peticion->withHeader('Idempotency-Key', $clave);
    }

    return $peticion->postJson(route('api.acopiador.entregas.store'), [
        'metodo_identificacion' => 'QR',
        'identificador' => $test->proveedor->qr_token,
        'litros' => 20,
        ...$extra,
    ]);
}

function instante(string $iso): int
{
    return (new DateTimeImmutable($iso))->getTimestamp();
}

it('29. online: la entrega queda con la hora del evento enviada por el teléfono', function () {
    $evento = '2026-10-27T11:00:40-05:00'; // pulsó Guardar 3 s antes de que llegue

    $r = postEntregaEvento($this, ['fecha_hora_evento' => $evento])->assertCreated();

    expect(instante($r->json('data.fecha_hora')))->toBe(instante($evento));
});

it('30. offline: acopio 09:35 sincronizado 11:00 → fecha_hora 09:35 y created_at 11:00', function () {
    $r = postEntregaEvento($this, ['fecha_hora_evento' => EVENTO_0935], 'local-1-abc')->assertCreated();

    expect(instante($r->json('data.fecha_hora')))->toBe(instante(EVENTO_0935));
    expect(instante($r->json('data.fecha_registro')))->toBe(instante('2026-10-27T11:00:43-05:00'));

    // Convenio del proyecto: la base guarda en UTC (09:35 Perú = 14:35 UTC), nunca 09:35 UTC.
    $fila = EntregaModel::findOrFail($r->json('data.id'));
    expect($fila->getRawOriginal('fecha_hora'))->toBe('2026-10-27 14:35:12');
});

it('31 y 33. reintento con la misma Idempotency-Key (respuesta perdida): una sola entrega y conserva 09:35', function () {
    $primera = postEntregaEvento($this, ['fecha_hora_evento' => EVENTO_0935], 'local-1-abc')->assertCreated();

    Carbon::setTestNow(Carbon::parse('2026-10-27T11:05:00-05:00'));
    $segunda = postEntregaEvento($this, ['fecha_hora_evento' => EVENTO_0935], 'local-1-abc')->assertCreated();

    expect($segunda->json('data.id'))->toBe($primera->json('data.id'));
    expect(instante($segunda->json('data.fecha_hora')))->toBe(instante(EVENTO_0935));
    $this->assertDatabaseCount('entregas', 1);
});

it('32. una hora del evento excesivamente futura responde 422 con mensaje entendible', function () {
    postEntregaEvento($this, ['fecha_hora_evento' => '2026-10-28T09:35:12-05:00'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fecha_hora_evento' => 'no puede estar en el futuro']);

    $this->assertDatabaseCount('entregas', 0);
});

it('tolera unos minutos de desfase del reloj del teléfono', function () {
    postEntregaEvento($this, ['fecha_hora_evento' => '2026-10-27T11:05:00-05:00'])->assertCreated();
});

it('exige el desfase horario en la hora del evento', function () {
    postEntregaEvento($this, ['fecha_hora_evento' => '2026-10-27T09:35:12'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fecha_hora_evento');
});

it('sin hora del evento (clientes antiguos) usa la hora del servidor', function () {
    $r = postEntregaEvento($this)->assertCreated();

    expect(instante($r->json('data.fecha_hora')))->toBe(instante('2026-10-27T11:00:43-05:00'));
});

it('35. los 10 minutos para corregir litros cuentan desde el acopio (09:35), no desde la sincronización', function () {
    $r = postEntregaEvento($this, ['fecha_hora_evento' => EVENTO_0935], 'local-1-abc')->assertCreated();

    Carbon::setTestNow(Carbon::parse('2026-10-27T11:01:00-05:00'));

    expect(fn () => app(CorregirLitrosEntrega::class)->execute($r->json('data.id'), 25.0, fixture_usuario('ADMINISTRADOR')->id))
        ->toThrow(DomainException::class, 'ya pasaron más de 10 minutos desde la hora de acopio');
});

it('el acopio desde un recorrido usa el mismo contrato fecha_hora_evento', function () {
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $this->zona->id, nombre: 'Ruta 1');
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $this->proveedor->id, ordenVisita: 1);
    $recorrido = app(AbrirRecorrido::class)->execute(rutaId: $ruta->id(), acopiadorId: $this->acopiador->id);
    $parada = RecorridoDetalleModel::where('recorrido_id', $recorrido->id())->firstOrFail();

    $r = $this->actingAs($this->acopiador->user, 'sanctum')
        ->postJson(route('api.acopiador.recorridos.paradas.acopio', [$recorrido->id(), $parada->id]), [
            'litros' => 20,
            'token_idempotencia' => 'local-2-xyz',
            'fecha_hora_evento' => EVENTO_0935,
        ])->assertCreated();

    expect(EntregaModel::findOrFail($r->json('data.entrega_id'))->getRawOriginal('fecha_hora'))->toBe('2026-10-27 14:35:12');
});
