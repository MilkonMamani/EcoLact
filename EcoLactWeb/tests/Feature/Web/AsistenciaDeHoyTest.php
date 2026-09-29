<?php

use App\Application\Pago\GenerarObligacionesPersonal;
use App\Application\Pago\MarcarFaltasAutomaticas;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // 10:00 UTC = 05:00 en Lima: antes del cierre de asistencia de las 07:00.
    $this->travelTo('2026-09-20 10:00:00');
});

/**
 * Cuatro trabajadores: Rosa presente por QR, Elena presente registrada a mano,
 * Abel con falta automática y David todavía pendiente.
 *
 * @return array{admin: User, acopiador: User, ventas: User, encargado: User, analista: User}
 */
function asistencia_escenario(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = pagos_personal('ACOPIADOR', 'Rosa');
    $ventas = pagos_personal('VENTAS', 'Elena');
    $encargado = pagos_personal('ENCARGADO_PLANTA', 'Abel');
    $analista = pagos_personal('CALIDAD', 'David');
    foreach (['ACOPIADOR', 'VENTAS', 'ENCARGADO_PLANTA', 'CALIDAD', 'ADMINISTRADOR'] as $rol) {
        pagos_configurar($rol, 900, '2026-09-01', $admin);
    }
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    JornadaPersonalModel::create([
        'trabajador_id' => asistencia_identidad($acopiador)->id, 'rol_id' => $acopiador->role_id, 'fecha' => '2026-09-20',
        'estado' => 'PRESENTE', 'origen' => 'QR', 'hora_marcacion' => '2026-09-20 07:52:00', 'registrado_por_user_id' => $admin->id,
    ]);
    pagos_jornada(asistencia_identidad($ventas)->id, '2026-09-20', $admin->id);
    pagos_falta(asistencia_identidad($encargado)->id, $encargado->role_id, '2026-09-20');

    return compact('admin', 'acopiador', 'ventas', 'encargado', 'analista');
}

function asistencia_identidad(User $usuario): TrabajadorModel
{
    return TrabajadorModel::where('user_id', $usuario->id)->firstOrFail();
}

it('muestra "Asistencia de hoy" con la fecha legible y Escanear QR, sin el ícono QR grande', function () {
    ['admin' => $admin] = asistencia_escenario();

    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertOk()
        ->assertSeeInOrder(['Dashboard', 'Asistencia', 'Asistencia de hoy', 'Registra y consulta la asistencia diaria del personal.', 'Domingo, 20 de septiembre de 2026'])
        ->assertSee('data-modal-open="modal-escanear-qr"', false)
        ->assertDontSee('h-14 w-14 shrink-0 items-center justify-center rounded-2xl', false);
});

it('resume trabajadores, presentes, faltas y pendientes con los datos reales', function () {
    ['admin' => $admin] = asistencia_escenario();

    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertSeeInOrder(['4', 'trabajadores', '2', 'presentes', '1', 'falta', '1', 'pendiente']);
});

it('no muestra nada sobre el cierre automático, antes ni después de las 07:00', function () {
    ['admin' => $admin] = asistencia_escenario();

    foreach (['2026-09-20 10:00:00', '2026-09-20 13:00:00'] as $momento) {
        $this->travelTo($momento);

        $this->actingAs($admin)->get(route('admin.asistencia.index'))
            ->assertOk()
            ->assertDontSee('cierre automático')
            ->assertDontSee('pendientes de registro')
            ->assertDontSee('pendiente de registro')
            ->assertDontSee('automáticamente');
    }
});

it('la falta automática sigue funcionando en el backend y su resultado se ve como Falta · Automático', function () {
    ['admin' => $admin, 'analista' => $analista] = asistencia_escenario();

    $this->travelTo('2026-09-20 12:00:00'); // 07:00 en Lima: cierre.
    app(MarcarFaltasAutomaticas::class)->execute();

    expect(JornadaPersonalModel::where('trabajador_id', asistencia_identidad($analista)->id)->whereDate('fecha', '2026-09-20')->value('origen'))->toBe('AUTOMATICO');

    $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertSeeInOrder(['4', 'trabajadores', '2', 'presentes', '2', 'faltas', '0', 'pendientes'])
        ->assertSeeInOrder(['David', 'Analista de calidad', 'Falta', '—', 'Automático']);
});

it('muestra roles, estados, hora y origen legibles, sin enums técnicos', function () {
    ['admin' => $admin] = asistencia_escenario();

    $html = $this->actingAs($admin)->get(route('admin.asistencia.index'))
        ->assertSeeInOrder(['Trabajador', 'Rol', 'DNI', 'Asistencia', 'Hora', 'Origen'])
        ->assertSeeInOrder(['Abel', 'Encargado de planta', 'Falta', '—', 'Automático'])
        ->assertSeeInOrder(['David', 'Analista de calidad', 'Pendiente', '—'])
        ->assertSeeInOrder(['Elena', 'Personal de ventas', 'Presente', 'Manual'])
        ->assertSeeInOrder(['Rosa', 'Acopiador', 'Presente', '07:52', 'QR'])
        ->getContent();

    $tabla = substr($html, strpos($html, '<table>'), strpos($html, '</table>') - strpos($html, '<table>'));
    expect($tabla)->not->toContain('ENCARGADO_PLANTA')->not->toContain('>CALIDAD<')->not->toContain('AUTOMATICO<')->not->toContain('>VENTAS<');
});

it('el menú de acciones ofrece corregir si hay jornada y registrar a mano si está pendiente', function () {
    ['admin' => $admin, 'analista' => $analista, 'encargado' => $encargado] = asistencia_escenario();

    $html = $this->actingAs($admin)->get(route('admin.asistencia.index'))->getContent();
    $filaDe = function (User $usuario) use ($html): string {
        $inicio = strpos($html, 'data-user-id="'.$usuario->id.'"');

        return substr($html, $inicio, strpos($html, '</tr>', $inicio) - $inicio);
    };

    expect($filaDe($analista))->toContain('data-asistencia-registrar')->not->toContain('data-asistencia-corregir');
    expect($filaDe($encargado))->toContain('data-asistencia-corregir')->not->toContain('data-asistencia-registrar');
    expect($html)->toContain('id="modal-registrar-asistencia"')->toContain('id="modal-corregir-asistencia"');
});

it('el filtro de rol usa nombres legibles y no ofrece al administrador', function () {
    ['admin' => $admin] = asistencia_escenario();

    $html = $this->actingAs($admin)->get(route('admin.asistencia.index'))->getContent();
    $filtro = substr($html, strpos($html, 'name="rol"'), 3000);
    $filtro = substr($filtro, 0, strpos($filtro, '</select>'));

    expect($filtro)->toContain('Acopiador')->toContain('Analista de calidad')->toContain('Encargado de planta')->toContain('Personal de ventas')
        ->not->toContain('Administrador')->not->toContain('ENCARGADO_PLANTA');
});

it('filtra por nombre o DNI, rol y fecha', function () {
    ['admin' => $admin, 'encargado' => $encargado] = asistencia_escenario();
    $this->actingAs($admin);

    $this->get(route('admin.asistencia.index', ['q' => 'Abel']))->assertSee('Abel Apellido')->assertDontSee('Rosa Apellido');
    $this->get(route('admin.asistencia.index', ['q' => asistencia_identidad($encargado)->dni]))->assertSee('Abel Apellido')->assertDontSee('Rosa Apellido');
    $this->get(route('admin.asistencia.index', ['rol' => $encargado->role_id]))->assertSee('Abel Apellido')->assertDontSee('David Apellido');
    $this->get(route('admin.asistencia.index', ['fecha' => '2026-09-19']))->assertSee('Consulta de asistencia');
});

it('una fecha anterior es consulta: sin Escanear QR, sin banners y con Pendiente para quien no marcó', function () {
    ['admin' => $admin] = asistencia_escenario();

    $this->actingAs($admin)->get(route('admin.asistencia.index', ['fecha' => '2026-09-19']))
        ->assertOk()
        ->assertSee('Consulta de asistencia')
        ->assertSee('Sábado, 19 de septiembre de 2026')
        ->assertSee('Volver a hoy')
        ->assertSeeInOrder(['4', 'trabajadores', '0', 'presentes', '0', 'faltas', '4', 'pendientes'])
        ->assertDontSee('data-modal-open="modal-escanear-qr"', false)
        ->assertDontSee('Consulta histórica');
});

it('pagina en el servidor y los contadores cuentan todo el filtro, no solo la página', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    foreach (range(1, 22) as $i) {
        pagos_personal('ACOPIADOR', sprintf('Persona %02d', $i));
    }
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    $this->actingAs($admin)->get(route('admin.asistencia.index', ['q' => 'Persona']))
        ->assertSee('Mostrando 1 a 20 de 22 trabajadores')
        ->assertSeeInOrder(['22', 'trabajadores', '0', 'presentes', '0', 'faltas', '22', 'pendientes'])
        ->assertSee(e(route('admin.asistencia.index', ['q' => 'Persona', 'pagina' => 2])), false);

    $this->get(route('admin.asistencia.index', ['q' => 'Persona', 'pagina' => 2]))
        ->assertSee('Mostrando 21 a 22 de 22 trabajadores')
        ->assertSee('Persona 22');
});

it('Asistencia aparece arriba en el menú, junto a Inicio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertSeeInOrder(['Dashboard', 'Inicio', 'Asistencia', 'Operación', 'Acopio', 'Proveedores', 'Zonas', 'Personal', 'Trabajadores'])
        ->assertDontSee('Control de asistencia');
});
