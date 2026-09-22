<?php

use App\Application\Pago\GenerarObligacionesPersonal;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/** Abre la ventana EN_CURSO de un acopiador y devuelve el usuario (para las rutas de jornadas). */
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
        'user_id' => $trabajador->id,
        'observacion' => 'Jornada completa',
        'registrado_por_user_id' => $admin->id,
    ]);
    expect(JornadaPersonalModel::where('user_id', $trabajador->id)->firstOrFail()->fecha->format('Y-m-d'))->toBe('2026-09-10');
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
    pagos_jornada($trabajador->id, $trabajador->role_id, '2026-09-05', $admin->id, 'Primera jornada');
    pagos_jornada($trabajador->id, $trabajador->role_id, '2026-09-06', $admin->id);

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
        ->assertSee('Días trabajados')
        ->assertSee('0/30');

    // El alta del administrador (fecha de creación del usuario) cae en "hoy"
    // (2026-09-20, congelado por travelTo), así que su ventana abre ese día:
    // el día trabajado se registra dentro de ese rango, no antes.
    $this->actingAs($admin)
        ->post(route('admin.trabajadores.jornadas.store', $admin->id), ['fecha' => '2026-09-20'])
        ->assertRedirect();

    $obligacion = ObligacionPagoModel::where('user_id', $admin->id)->where('tipo_beneficiario', 'PERSONAL')->firstOrFail();
    expect((int) $obligacion->dias_trabajados)->toBe(1);
});
