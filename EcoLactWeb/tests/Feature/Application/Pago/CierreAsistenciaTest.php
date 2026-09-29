<?php

use App\Application\Acopiador\DesactivarAcopiador;
use App\Application\Acopiador\ReactivarAcopiador;
use App\Application\Pago\CerrarNominaMensual;
use App\Application\Pago\GenerarObligacionesPersonal;
use App\Application\Pago\MarcarFaltasAutomaticas;
use App\Application\Pago\ObtenerControlPeriodoTrabajador;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * Regla oficial: la asistencia de un día se registra o corrige solo antes de
 * las 07:00 (hora de Lima = 12:00 UTC). Desde las 07:00 el día queda cerrado y
 * quien debía asistir sin registro pasa a FALTA. Los días antes del ingreso o
 * en que estuvo inactivo "no correspondían": nunca son falta ni día pagable.
 */

/** Momento en hora de Lima (la app corre en UTC). */
function lima(string $momento): string
{
    return CarbonImmutable::parse($momento, 'America/Lima')->utc()->format('Y-m-d H:i:s');
}

function cierre_acopiador(User $admin, string $alta = '2026-09-01'): User
{
    $persona = pagos_personal('ACOPIADOR', 'Yanil', $alta);
    pagos_configurar('ACOPIADOR', 900, '2026-08-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable(now()->format('Y-m-d')));

    return $persona;
}

function cierre_identidad(User $persona): TrabajadorModel
{
    return TrabajadorModel::where('user_id', $persona->id)->firstOrFail();
}

/** @return list<string> */
function cierre_fechas(User $persona, string $estado): array
{
    return JornadaPersonalModel::where('trabajador_id', cierre_identidad($persona)->id)
        ->where('estado', $estado)
        ->orderBy('fecha')
        ->pluck('fecha')
        ->map(fn ($fecha): string => $fecha->format('Y-m-d'))
        ->all();
}

// ------------------------------------------------------ 06:59 / 07:00 / 07:01

it('a las 06:59 un trabajador sin asistencia sigue Pendiente y no recibe falta', function () {
    $this->travelTo(lima('2026-09-27 06:59:00'));
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = cierre_acopiador($admin, '2026-09-27');

    app(MarcarFaltasAutomaticas::class)->execute();

    expect(cierre_fechas($persona, 'FALTA'))->toBe([]);
    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertSeeInOrder(['Yanil', 'Pendiente'])
        ->assertSee('data-modal-open="modal-escanear-qr"', false)
        ->assertSee('data-asistencia-registrar', false);
});

it('a las 07:00 en punto quien no registró nada pasa a Falta automática (hora nula)', function () {
    $this->travelTo(lima('2026-09-27 06:30:00'));
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = cierre_acopiador($admin, '2026-09-27');

    $this->travelTo(lima('2026-09-27 07:00:00'));
    app(MarcarFaltasAutomaticas::class)->execute();

    $falta = JornadaPersonalModel::where('trabajador_id', cierre_identidad($persona)->id)->firstOrFail();
    expect($falta->fecha->format('Y-m-d'))->toBe('2026-09-27')
        ->and($falta->estado)->toBe('FALTA')
        ->and($falta->origen)->toBe('AUTOMATICO')
        ->and($falta->hora_marcacion)->toBeNull();
});

it('a las 07:01, aunque el scheduler no haya corrido, la pantalla ya muestra Falta y nunca Pendiente', function () {
    $this->travelTo(lima('2026-09-27 06:30:00'));
    $admin = fixture_usuario('ADMINISTRADOR');
    cierre_acopiador($admin, '2026-09-27');

    $this->travelTo(lima('2026-09-27 07:01:00'));

    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertSeeInOrder(['Yanil', 'Falta', 'Automático'])
        ->assertDontSee('data-modal-open="modal-escanear-qr"', false)
        ->assertSee('data-asistencia-ver', false)
        ->assertDontSee('data-asistencia-registrar', false)
        ->assertDontSee('data-asistencia-corregir', false);
});

it('la falta automática es idempotente: correrla a las 07:00, 07:05 y 07:10 deja una sola jornada por día', function () {
    $this->travelTo(lima('2026-09-25 06:00:00'));
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = cierre_acopiador($admin, '2026-09-25');

    foreach (['07:00:00', '07:05:00', '07:10:00'] as $hora) {
        $this->travelTo(lima('2026-09-27 '.$hora));
        app(MarcarFaltasAutomaticas::class)->execute();
    }

    expect(cierre_fechas($persona, 'FALTA'))->toBe(['2026-09-25', '2026-09-26', '2026-09-27'])
        ->and(JornadaPersonalModel::where('trabajador_id', cierre_identidad($persona)->id)->count())->toBe(3);
});

// ------------------------------------------------------ registrar / corregir / QR

it('antes de las 07:00 el administrador registra presente y corrige, con auditoría completa', function () {
    $this->travelTo(lima('2026-09-27 06:20:00'));
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = cierre_acopiador($admin, '2026-09-27');

    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.store', $persona->id), ['fecha' => '2026-09-27'])
        ->assertSessionHas('success');
    expect(cierre_fechas($persona, 'PRESENTE'))->toBe(['2026-09-27']);

    $this->travelTo(lima('2026-09-27 06:50:00'));
    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.corregir', [$persona->id, '2026-09-27']), ['estado' => 'FALTA', 'motivo' => 'Se retiró antes de iniciar'])
        ->assertSessionHas('success');

    $jornada = JornadaPersonalModel::where('trabajador_id', cierre_identidad($persona)->id)->firstOrFail();
    expect($jornada->estado)->toBe('FALTA');

    $auditoria = DB::table('auditorias')->where('accion', 'CORRECCION_ASISTENCIA')->where('registro_id', $jornada->id)->first();
    expect($auditoria)->not->toBeNull()
        ->and($auditoria->user_id)->toBe($admin->id)
        ->and($auditoria->motivo)->toBe('Se retiró antes de iniciar')
        ->and($auditoria->created_at)->not->toBeNull()
        ->and(json_decode($auditoria->datos_anteriores, true))->toMatchArray(['estado' => 'PRESENTE', 'trabajador_id' => cierre_identidad($persona)->id, 'fecha' => '2026-09-27'])
        ->and(json_decode($auditoria->datos_nuevos, true))->toMatchArray(['estado' => 'FALTA']);
});

it('desde las 07:00 el backend rechaza registrar o corregir, aunque la petición se arme a mano', function () {
    $this->travelTo(lima('2026-09-27 06:00:00'));
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = cierre_acopiador($admin, '2026-09-26');
    pagos_jornada(cierre_identidad($persona)->id, '2026-09-26', $admin->id);

    $this->travelTo(lima('2026-09-27 07:00:00'));

    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.store', $persona->id), ['fecha' => '2026-09-27'])
        ->assertSessionHasErrors(['jornada' => 'La asistencia de hoy ya se cerró a las 07:00 a. m.: solo se puede consultar.']);

    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.corregir', [$persona->id, '2026-09-26']), ['estado' => 'FALTA', 'motivo' => 'Intento tardío'])
        ->assertSessionHasErrors('jornada');

    expect(cierre_fechas($persona, 'PRESENTE'))->toBe(['2026-09-26'])
        ->and(DB::table('auditorias')->where('accion', 'CORRECCION_ASISTENCIA')->count())->toBe(0);
});

it('el QR registra presente antes de las 07:00 y se bloquea desde las 07:00', function () {
    $this->travelTo(lima('2026-09-27 06:40:00'));
    $admin = fixture_usuario('ADMINISTRADOR');
    $vanessa = cierre_acopiador($admin, '2026-09-27');
    $yanil = pagos_personal('ACOPIADOR', 'Otro', '2026-09-27');
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-27'));

    $this->actingAs($admin)->postJson(route('api.admin.asistencia.qr'), ['qr_token' => cierre_identidad($vanessa)->qr_token])
        ->assertCreated()
        ->assertJsonPath('jornada.estado', 'PRESENTE');

    $this->travelTo(lima('2026-09-27 07:00:00'));

    $this->actingAs($admin)->postJson(route('api.admin.asistencia.qr'), ['qr_token' => cierre_identidad($yanil)->qr_token])
        ->assertStatus(422)
        ->assertJsonPath('message', 'La asistencia de hoy ya se cerró a las 07:00 a. m.: solo se puede consultar.');

    expect(cierre_fechas($yanil, 'PRESENTE'))->toBe([]);
});

// ------------------------------------------------------ ingreso, inactividad y reactivación

it('antes de su fecha de ingreso los días no correspondían: nunca son falta', function () {
    $this->travelTo(lima('2026-09-20 08:00:00'));
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = cierre_acopiador($admin, '2026-09-14');

    app(MarcarFaltasAutomaticas::class)->execute();

    expect(cierre_fechas($persona, 'FALTA'))->toBe(['2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19', '2026-09-20']);

    $control = app(ObtenerControlPeriodoTrabajador::class)->execute($persona->id);
    $estados = collect($control->dias)->pluck('estado', 'fechaIso');
    expect($estados['2026-09-01'])->toBe('NO_APLICABLE')
        ->and($estados['2026-09-13'])->toBe('NO_APLICABLE')
        ->and($estados['2026-09-14'])->toBe('FALTA');
});

it('inactivo no acumula faltas ni pago; reactivado cuenta solo desde su reactivación, sin faltas retroactivas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');

    $this->travelTo(lima('2026-09-01 06:00:00'));
    $persona = cierre_acopiador($admin, '2026-09-01');
    foreach (range(1, 10) as $dia) {
        pagos_jornada(cierre_identidad($persona)->id, sprintf('2026-09-%02d', $dia), $admin->id);
    }

    // Se desactiva el 11 antes de las 07:00 (sin marcar): su último día activo fue el 10.
    $this->travelTo(lima('2026-09-11 06:00:00'));
    app(DesactivarAcopiador::class)->execute($persona->acopiador->id);

    $this->travelTo(lima('2026-09-15 09:00:00'));
    app(MarcarFaltasAutomaticas::class)->execute();
    expect(cierre_fechas($persona, 'FALTA'))->toBe([]);

    // Se reactiva el 21 antes de las 07:00: cuenta desde el 21.
    $this->travelTo(lima('2026-09-21 06:00:00'));
    app(ReactivarAcopiador::class)->execute($persona->acopiador->id, $zona->id);

    $this->travelTo(lima('2026-09-22 08:00:00'));
    app(MarcarFaltasAutomaticas::class)->execute();

    expect(cierre_fechas($persona, 'FALTA'))->toBe(['2026-09-21', '2026-09-22'])
        ->and(DB::table('trabajador_periodos_actividad')->where('trabajador_id', cierre_identidad($persona)->id)->orderBy('fecha_inicio')->get(['fecha_inicio', 'fecha_fin'])->map(fn ($p) => [substr($p->fecha_inicio, 0, 10), $p->fecha_fin ? substr($p->fecha_fin, 0, 10) : null])->all())
        ->toBe([['2026-09-01', '2026-09-10'], ['2026-09-21', null]]);

    $control = app(ObtenerControlPeriodoTrabajador::class)->execute($persona->id, '2026-09');
    $estados = collect($control->dias)->pluck('estado', 'fechaIso');
    expect($estados['2026-09-10'])->toBe('PRESENTE')
        ->and($estados['2026-09-11'])->toBe('NO_APLICABLE')
        ->and($estados['2026-09-20'])->toBe('NO_APLICABLE')
        ->and($estados['2026-09-21'])->toBe('FALTA')
        ->and($control->asistencias)->toBe(10)
        ->and($control->faltas)->toBe(2)
        ->and($control->esMesCompleto)->toBeFalse();

    // Pago: solo días realmente trabajados (10 × 30), nunca los días inactivos.
    expect((float) ObligacionPagoModel::where('user_id', $persona->id)->value('acumulado'))->toBe(300.0);
});

it('reactivado después de las 07:00 empieza a contar desde el día siguiente (ese día ya estaba cerrado)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');

    $this->travelTo(lima('2026-09-01 06:00:00'));
    $persona = cierre_acopiador($admin, '2026-09-01');
    pagos_jornada(cierre_identidad($persona)->id, '2026-09-01', $admin->id);
    $this->travelTo(lima('2026-09-02 06:00:00'));
    app(DesactivarAcopiador::class)->execute($persona->acopiador->id);

    $this->travelTo(lima('2026-09-21 10:00:00'));
    app(ReactivarAcopiador::class)->execute($persona->acopiador->id, $zona->id);
    app(MarcarFaltasAutomaticas::class)->execute();

    expect(cierre_fechas($persona, 'FALTA'))->toBe([]);

    $this->travelTo(lima('2026-09-22 07:30:00'));
    app(MarcarFaltasAutomaticas::class)->execute();

    expect(cierre_fechas($persona, 'FALTA'))->toBe(['2026-09-22']);
});

it('el cierre mensual solo paga días válidos: no rellena faltas en periodos inactivos ni cuenta faltas viejas de esos días', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');

    $this->travelTo(lima('2026-09-01 06:00:00'));
    $persona = cierre_acopiador($admin, '2026-09-01');
    foreach (range(1, 5) as $dia) {
        pagos_jornada(cierre_identidad($persona)->id, sprintf('2026-09-%02d', $dia), $admin->id);
    }
    $this->travelTo(lima('2026-09-06 06:00:00'));
    app(DesactivarAcopiador::class)->execute($persona->acopiador->id);

    // Falta vieja generada antes del historial de actividad en un día inactivo: no debe contar.
    pagos_falta(cierre_identidad($persona)->id, $persona->role_id, '2026-09-10');

    $this->travelTo(lima('2026-09-26 06:00:00'));
    app(ReactivarAcopiador::class)->execute($persona->acopiador->id, $zona->id);
    foreach (range(26, 30) as $dia) {
        pagos_jornada(cierre_identidad($persona)->id, sprintf('2026-09-%02d', $dia), $admin->id);
    }

    $this->travelTo(lima('2026-10-01 00:05:00'));
    app(CerrarNominaMensual::class)->execute(new DateTimeImmutable('2026-10-01'));

    $septiembre = ObligacionPagoModel::where('user_id', $persona->id)->whereDate('periodo_inicio', '2026-09-01')->firstOrFail();
    expect($septiembre->estado)->toBe('PENDIENTE')
        ->and((int) $septiembre->dias_trabajados)->toBe(10)
        ->and((float) $septiembre->acumulado)->toBe(300.0)
        ->and(cierre_fechas($persona, 'FALTA'))->toBe(['2026-09-10']);
});
