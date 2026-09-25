<?php

use App\Application\Pago\GenerarObligacionesPersonal;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/** Abre la obligación EN_CURSO del mes de un acopiador y devuelve el usuario (para las rutas de jornadas, que siguen identificando por User). */
function acopiador_con_ventana_abierta(User $admin, string $nombre = 'Rusman'): User
{
    $persona = pagos_personal('ACOPIADOR', $nombre);
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect(ObligacionPagoModel::where('user_id', $persona->id)->exists())->toBeTrue();

    return $persona;
}

it('el administrador registra una jornada desde el perfil de un trabajador', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_con_ventana_abierta($admin);
    $identidad = TrabajadorModel::where('user_id', $trabajador->id)->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.store', $trabajador->id), [
            'fecha' => '2026-09-10',
            'observacion' => 'Jornada completa',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $obligacion = ObligacionPagoModel::where('user_id', $trabajador->id)->firstOrFail();
    expect((int) $obligacion->dias_trabajados)->toBe(1);
    $this->assertDatabaseHas('jornadas_personal', [
        'trabajador_id' => $identidad->id,
        'estado' => 'PRESENTE',
        'origen' => 'MANUAL',
        'observacion' => 'Jornada completa',
        'registrado_por_user_id' => $admin->id,
    ]);
    expect(JornadaPersonalModel::where('trabajador_id', $identidad->id)->firstOrFail()->fecha->format('Y-m-d'))->toBe('2026-09-10');
});

it('rechaza registrar dos veces la misma fecha desde el formulario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_con_ventana_abierta($admin);

    $this->actingAs($admin)->post(route('admin.trabajadores.jornadas.store', $trabajador->id), ['fecha' => '2026-09-10']);
    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.store', $trabajador->id), ['fecha' => '2026-09-10'])
        ->assertSessionHasErrors('jornada');

    expect(ObligacionPagoModel::where('user_id', $trabajador->id)->firstOrFail()->dias_trabajados)->toBe(1);
});

it('"Ver jornadas" muestra las fechas ya registradas del periodo actual', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_con_ventana_abierta($admin);
    $identidad = TrabajadorModel::where('user_id', $trabajador->id)->firstOrFail();
    pagos_jornada($identidad->id, '2026-09-05', $admin->id, 'Primera jornada');
    pagos_jornada($identidad->id, '2026-09-06', $admin->id);

    $this->actingAs($admin)->get(route('admin.trabajadores.jornadas.index', $trabajador->id))
        ->assertOk()
        ->assertSee('05/09/2026')
        ->assertSee('06/09/2026')
        ->assertSee('Primera jornada')
        ->assertSee('2 días trabajados')
        ->assertDontSee('<html', false);
});

it('un usuario no administrador no puede registrar ni ver jornadas', function (string $rol) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_con_ventana_abierta($admin);
    $usuario = fixture_usuario($rol);

    $this->actingAs($usuario)->get(route('admin.trabajadores.jornadas.index', $trabajador->id))->assertForbidden();
    $this->actingAs($usuario)->post(route('admin.trabajadores.jornadas.store', $trabajador->id), ['fecha' => '2026-09-10'])->assertForbidden();

    expect(ObligacionPagoModel::where('user_id', $trabajador->id)->firstOrFail()->dias_trabajados)->toBe(0);
})->with(['ACOPIADOR', 'PROVEEDOR', 'CALIDAD']);

it('el administrador comparte la misma lógica de jornadas para su propio perfil ("Mi perfil")', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ADMINISTRADOR', 1500, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    $this->actingAs($admin)->get(route('admin.administrador.show'))
        ->assertOk()
        ->assertSee('Código QR de asistencia')
        ->assertSee('Resumen del mes')
        ->assertSee('Asistencias')
        ->assertSee('Pago proporcional según los días trabajados desde su fecha de ingreso');

    // El alta del administrador (fecha de creación del usuario) cae en "hoy"
    // (2026-09-20, congelado por travelTo): no estuvo activo todo septiembre,
    // así que el día trabajado se registra dentro de su rango, no antes.
    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.store', $admin->id), ['fecha' => '2026-09-20'])
        ->assertRedirect();

    $obligacion = ObligacionPagoModel::where('user_id', $admin->id)->where('tipo_beneficiario', 'PERSONAL')->firstOrFail();
    expect((int) $obligacion->dias_trabajados)->toBe(1);
});

it('el calendario del perfil marca los días con jornada real y nunca expone jerga técnica', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $trabajador = acopiador_con_ventana_abierta($admin, 'Milkon');
    $identidad = TrabajadorModel::where('user_id', $trabajador->id)->firstOrFail();
    pagos_jornada($identidad->id, '2026-09-01', $admin->id);

    $html = $this->actingAs($admin)->get(route('admin.acopiadores.show', $trabajador->acopiador->id))
        ->assertOk()
        ->assertSee('Código QR de asistencia')
        ->getContent();

    expect($html)->toContain('data-fecha="2026-09-01"')
        ->toContain('data-estado="PRESENTE"')
        ->not->toContain('Devengado')
        ->not->toContain('periodo_inicio');
});

it('un trabajador que ingresó a mitad de mes ve "Pago proporcional" en su resumen', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $diana = pagos_personal('ACOPIADOR', 'Diana Nina', '2026-09-15');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));
    $identidad = TrabajadorModel::where('user_id', $diana->id)->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.acopiadores.show', $identidad->user->acopiador->id))
        ->assertOk()
        ->assertSee('No correspondía')
        ->assertSee('Pago proporcional según los días trabajados desde su fecha de ingreso (15/09/2026)')
        ->assertDontSee('Descuento por faltas');
});
