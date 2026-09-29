<?php

use App\Application\Pago\GenerarObligacionesPersonal;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/** Sincroniza `trabajadores` y abre la obligación del mes de un acopiador con sueldo configurado. */
function acopiador_sincronizado(User $admin, string $nombre = 'Rusman'): TrabajadorModel
{
    $persona = pagos_personal('ACOPIADOR', $nombre);
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    return TrabajadorModel::where('user_id', $persona->id)->firstOrFail();
}

it('lista a los trabajadores vigentes de hoy con su estado de asistencia', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin, 'Rusman Torres');
    pagos_jornada($trabajador->id, '2026-09-20', $admin->id);

    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertOk()
        ->assertSee('Asistencia de hoy')
        ->assertSeeInOrder(['Trabajador', 'Rol', 'DNI', 'Asistencia', 'Hora', 'Origen'])
        ->assertSeeInOrder(['Rusman Torres', 'Acopiador', 'Presente'])
        ->assertDontSee('>ACOPIADOR<', false);
});

it('un trabajador sin marcar hoy aparece como pendiente de registro, no como falta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    acopiador_sincronizado($admin, 'Sin Marcar');

    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertOk()
        ->assertSee('Sin Marcar')
        ->assertSeeInOrder(['Sin Marcar', 'Pendiente'])
        ->assertDontSee('Pendiente de registro</span>', false)
        // "Automático" (origen) solo existe si hay una jornada real.
        ->assertDontSee('Automático');
});

it('una falta automática ya registrada se ve como Falta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin, 'Con Falta');
    pagos_falta($trabajador->id, $trabajador->user->role_id, '2026-09-20');

    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertOk()
        ->assertSee('Con Falta')
        ->assertSee('Falta')
        ->assertSee('Automático');
});

it('busca por nombre y por DNI, nunca por username', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin, 'Yanil Perez');

    $this->actingAs($admin)->get(route('admin.asistencia.index', ['q' => 'Yanil']))
        ->assertOk()->assertSee('Yanil Perez');

    $this->actingAs($admin)->get(route('admin.asistencia.index', ['q' => $trabajador->dni]))
        ->assertOk()->assertSee('Yanil Perez');

    $this->actingAs($admin)->get(route('admin.asistencia.index', ['q' => 'no-existe-xyz']))
        ->assertOk()->assertDontSee('Yanil Perez');
});

it('un trabajador antes de su fecha de ingreso no aparece en la lista del día', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Futuro Ingreso', '2026-10-01');
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertOk()
        ->assertDontSee('Futuro Ingreso');
});

it('un usuario no administrador no puede ver Control de asistencia', function (string $rol) {
    $admin = fixture_usuario('ADMINISTRADOR');
    acopiador_sincronizado($admin);
    $usuario = fixture_usuario($rol);

    $this->actingAs($usuario)->get(route('admin.asistencia.index'))->assertForbidden();
})->with(['ACOPIADOR', 'PROVEEDOR', 'CALIDAD']);

// --------------------------------------------------------------- QR ---

it('el QR registra PRESENTE la primera vez', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin);

    $this->actingAs($admin)
        ->postJson(route('api.admin.asistencia.qr'), ['qr_token' => $trabajador->qr_token])
        ->assertCreated()
        ->assertJsonPath('duplicado', false);

    $this->assertDatabaseHas('jornadas_personal', [
        'trabajador_id' => $trabajador->id,
        'estado' => 'PRESENTE',
        'origen' => 'QR',
    ]);
});

it('un segundo escaneo el mismo día no duplica y responde idempotente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin);

    $this->actingAs($admin)->postJson(route('api.admin.asistencia.qr'), ['qr_token' => $trabajador->qr_token])->assertCreated();

    $this->actingAs($admin)
        ->postJson(route('api.admin.asistencia.qr'), ['qr_token' => $trabajador->qr_token])
        ->assertOk()
        ->assertJsonPath('duplicado', true);

    expect(JornadaPersonalModel::where('trabajador_id', $trabajador->id)->count())->toBe(1);
});

it('escanear un QR con una falta automática ya registrada exige corrección (409), sin pisarla', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin);
    pagos_falta($trabajador->id, $trabajador->user->role_id, '2026-09-20');

    $this->actingAs($admin)
        ->postJson(route('api.admin.asistencia.qr'), ['qr_token' => $trabajador->qr_token])
        ->assertStatus(409);

    $this->assertDatabaseHas('jornadas_personal', ['trabajador_id' => $trabajador->id, 'estado' => 'FALTA']);
});

it('un token QR desconocido devuelve un error legible', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)
        ->postJson(route('api.admin.asistencia.qr'), ['qr_token' => 'token-inexistente'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'QR no reconocido.');
});

// --------------------------------------------------------- corrección ---

it('el administrador corrige FALTA a PRESENTE con motivo y queda auditado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin);
    pagos_falta($trabajador->id, $trabajador->user->role_id, '2026-09-20');
    $jornada = JornadaPersonalModel::where('trabajador_id', $trabajador->id)->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.corregir', [$trabajador->user_id, '2026-09-20']), [
            'estado' => 'PRESENTE',
            'motivo' => 'Marcó tarde, olvidó escanear el QR',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('jornadas_personal', ['id' => $jornada->id, 'estado' => 'PRESENTE', 'origen' => 'CORREGIDO']);
    $this->assertDatabaseHas('auditorias', [
        'accion' => 'CORRECCION_ASISTENCIA',
        'tabla_afectada' => 'jornadas_personal',
        'registro_id' => $jornada->id,
        'motivo' => 'Marcó tarde, olvidó escanear el QR',
        'user_id' => $admin->id,
    ]);
});

it('la corrección exige un motivo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin);
    pagos_falta($trabajador->id, $trabajador->user->role_id, '2026-09-20');

    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.corregir', [$trabajador->user_id, '2026-09-20']), ['estado' => 'PRESENTE'])
        ->assertSessionHasErrors('motivo');
});

it('un usuario no administrador no puede corregir asistencia', function (string $rol) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_sincronizado($admin);
    pagos_falta($trabajador->id, $trabajador->user->role_id, '2026-09-20');
    $usuario = fixture_usuario($rol);

    $this->actingAs($usuario)
        ->post(route('admin.trabajadores.jornadas.corregir', [$trabajador->user_id, '2026-09-20']), [
            'estado' => 'PRESENTE', 'motivo' => 'Intento no autorizado',
        ])
        ->assertForbidden();
})->with(['ACOPIADOR', 'PROVEEDOR', 'CALIDAD']);
