<?php

use App\Application\Pago\Contracts\TrabajadoresPagoListadoQuery;
use App\Application\Pago\LimpiarObligacionesPersonalInvalidas;
use App\Application\Pago\TrabajadoresPagoFiltro;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/**
 * Simula a un administrador entrando a /admin/pagos/trabajadores: la
 * sincronización de obligaciones debe ocurrir sola (sin invocar el use case
 * ni el comando a mano), exactamente como la vería un usuario real.
 */
function visitar_pagar_a_trabajadores(User $admin, array $query = [])
{
    return test()->actingAs($admin)->get(route('admin.pagos.trabajadores.index', $query));
}

/**
 * Crea un trabajador ACOPIADOR activo con sueldo configurado, visita la
 * pantalla para que su obligación EN_CURSO se abra sola, y la fuerza a
 * PENDIENTE (mes completo, sin faltas) para que quede lista para pagar. Los
 * tests de esta pantalla no prueban el control de jornadas ni el cierre
 * mensual en sí (eso lo cubren JornadaPersonalTest, RegistrarJornadaTrabajadorTest
 * y CerrarNominaMensualTest), así que fuerzan el estado directamente en vez
 * de depender de viajar hasta fin de mes.
 */
function trabajador_pendiente(User $admin, string $nombre = 'Julio', float $sueldo = 900.0): ObligacionPagoModel
{
    pagos_personal('ACOPIADOR', $nombre);
    pagos_configurar('ACOPIADOR', $sueldo, '2026-09-01', $admin);
    visitar_pagar_a_trabajadores($admin);

    $obligacion = ObligacionPagoModel::where('beneficiario_nombre', 'like', $nombre.'%')->firstOrFail();

    return forzar_pendiente($obligacion, $sueldo);
}

/** Fuerza una obligación EN_CURSO a PENDIENTE con el acumulado del sueldo completo, sin pasar por el cierre real. */
function forzar_pendiente(ObligacionPagoModel $obligacion, ?float $sueldo = null): ObligacionPagoModel
{
    $sueldo ??= (float) ($obligacion->base_calculo['sueldo_base'] ?? 0.0);
    $valorDia = round($sueldo / 30, 2);

    $obligacion->update([
        'dias_trabajados' => 0,
        'valor_dia' => $valorDia,
        'acumulado' => $sueldo,
        'monto_calculado' => $sueldo,
        'estado' => 'PENDIENTE',
    ]);

    return $obligacion->fresh();
}

// ------------------------------------------------- CASO 1: generación real

it('CASO 1: sincroniza sola 3 ventanas EN_CURSO para 3 acopiadores activos con sueldo vigente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Yanil Perez');
    pagos_personal('ACOPIADOR', 'Vanessa Huanca');
    pagos_personal('ACOPIADOR', 'Rusman Torres');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);

    $response = visitar_pagar_a_trabajadores($admin);

    $response->assertOk()
        ->assertSeeInOrder(['Rusman Torres', 'Vanessa Huanca', 'Yanil Perez'])
        ->assertSee('S/ 1,500.00');

    expect(ObligacionPagoModel::where('tipo_beneficiario', 'PERSONAL')->where('estado', 'EN_CURSO')->count())->toBe(3);
});

// ------------------------------------------------------- CASO 2: idempotente

it('CASO 2: visitar la pantalla varias veces no duplica obligaciones', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Yanil Perez');
    pagos_personal('ACOPIADOR', 'Vanessa Huanca');
    pagos_personal('ACOPIADOR', 'Rusman Torres');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);

    visitar_pagar_a_trabajadores($admin);
    visitar_pagar_a_trabajadores($admin);
    visitar_pagar_a_trabajadores($admin);

    expect(ObligacionPagoModel::count())->toBe(3);
});

// --------------------------------------------- CASO 3 y 4: sin configurar

it('CASO 3: un rol remunerable sin sueldo configurado no genera obligación, pero el trabajador igual se muestra "Sin configurar"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('CALIDAD', 'David Lopez');

    $response = visitar_pagar_a_trabajadores($admin);

    $response->assertOk()
        ->assertSeeInOrder(['David Lopez', 'Analista de calidad', 'Sin configurar'])
        ->assertSee('No hay sueldos configurados para este periodo.')
        ->assertDontSee('Configurar sueldo');

    expect(ObligacionPagoModel::count())->toBe(0);
});

it('CASO 4: configurar el rol y volver a sincronizar genera exactamente una obligación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('CALIDAD', 'David Lopez');
    visitar_pagar_a_trabajadores($admin);

    pagos_configurar('CALIDAD', 1700, '2026-09-01', $admin);
    $response = visitar_pagar_a_trabajadores($admin);

    $response->assertOk()->assertSee('David Lopez')->assertSee('S/ 1,700.00');

    $obligaciones = ObligacionPagoModel::where('beneficiario_nombre', 'like', 'David Lopez%')->get();
    expect($obligaciones)->toHaveCount(1)
        ->and($obligaciones->first()->estado)->toBe('EN_CURSO');
});

// ---------------------------------------------------- CASO 5: no remunerable

it('CASO 5: un proveedor nunca genera sueldo mensual de personal', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_proveedor(fixture_zona());

    visitar_pagar_a_trabajadores($admin);

    expect(ObligacionPagoModel::where('tipo_beneficiario', 'PERSONAL')->count())->toBe(0);
    expect(RoleModel::where('nombre', 'PROVEEDOR')->value('es_remunerable'))->toBeFalsy();
});

// ------------------------------------------------------ CASO 6: inactivo

it('CASO 6: un trabajador inactivo no genera obligación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Ex Trabajador', estado: 'INACTIVO');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);

    $response = visitar_pagar_a_trabajadores($admin);

    $response->assertOk()->assertDontSee('Ex Trabajador');
    expect(ObligacionPagoModel::count())->toBe(0);
});

// --------------------------------------------------- CASO 7: pago histórico

it('CASO 7: un pago ya confirmado conserva el sueldo con el que se generó, aunque el rol cambie de precio después', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 1500);

    test()->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
        'metodo_pago' => 'EFECTIVO',
        'fecha_pago' => '2026-09-20',
    ]);

    ConfiguracionPagoModel::query()->update(['monto_base' => 9000]);
    visitar_pagar_a_trabajadores($admin);

    $obligacion->refresh();
    expect((float) $obligacion->monto_calculado)->toBe(1500.0)
        ->and($obligacion->estado)->toBe('PAGADO');
});

// ----------------------------------------------------- CASO 8: mes actual

it('CASO 8: sin parámetro de periodo, la pantalla asume el mes actual del servidor (septiembre 2026)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    trabajador_pendiente($admin, 'Julio');

    $response = visitar_pagar_a_trabajadores($admin);

    $response->assertOk()
        ->assertSee('Septiembre 2026')
        ->assertSee('value="2026-09"', false);
});

// ------------------------------------------------------- CASO 9: filtro rol

it('CASO 9: el filtro de rol devuelve únicamente ese rol', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    trabajador_pendiente($admin, 'Julio');
    pagos_personal('CALIDAD', 'Ruth');
    pagos_configurar('CALIDAD', 1100, '2026-09-01', $admin);
    visitar_pagar_a_trabajadores($admin);

    $rolAcopiador = RoleModel::where('nombre', 'ACOPIADOR')->firstOrFail();

    visitar_pagar_a_trabajadores($admin, ['rol' => $rolAcopiador->id])
        ->assertOk()->assertSee('Julio Apellido')->assertDontSee('Ruth Apellido');
});

// ---------------------------------------------------- CASO 10: filtro estado

it('CASO 10: el filtro de estado pendiente devuelve únicamente pendientes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio');
    pagos_personal('ACOPIADOR', 'Otro');
    visitar_pagar_a_trabajadores($admin);
    forzar_pendiente(ObligacionPagoModel::where('beneficiario_nombre', 'like', 'Otro%')->firstOrFail());

    test()->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
        'metodo_pago' => 'EFECTIVO',
        'fecha_pago' => '2026-09-20',
    ]);

    visitar_pagar_a_trabajadores($admin, ['estado' => 'PENDIENTE'])
        ->assertOk()->assertSee('Otro Apellido')->assertDontSee('Julio Apellido');
});

// ------------------------------------------------------- CASO 11: buscar DNI

it('CASO 11: buscar por DNI devuelve al trabajador correspondiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    trabajador_pendiente($admin, 'Rusman Torres');
    $dni = AcopiadorModel::where('nombres', 'like', 'Rusman%')->value('dni');

    visitar_pagar_a_trabajadores($admin, ['q' => $dni])
        ->assertOk()->assertSee('Rusman Torres');
});

// -------------------------------------------------- CASO 12: doble pago

it('CASO 12: pagar una obligación ya pagada falla correctamente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio');

    test()->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
        'metodo_pago' => 'EFECTIVO',
        'fecha_pago' => '2026-09-20',
    ]);

    test()->actingAs($admin)
        ->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
            'metodo_pago' => 'EFECTIVO',
            'fecha_pago' => '2026-09-20',
        ])
        ->assertSessionHasErrors('pago');

    $obligacion->refresh();
    expect($obligacion->estado)->toBe('PAGADO');
    $this->assertDatabaseCount('pagos', 1);
});

// --------------------------------------------------- estados vacíos (CASO 28)

it('si los trabajadores con sueldo configurado ya no están activos muestra el mensaje correspondiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $julio = pagos_personal('ACOPIADOR', 'Julio');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);
    $julio->update(['estado' => 'INACTIVO']);

    visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertSee('No hay trabajadores activos para las configuraciones disponibles.');
});

// ----------------------------------------------------------------- panel

it('el panel de un trabajador se sirve sin layout con su comprobante', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 1200);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $obligacion->id))
        ->assertOk()
        ->assertSee('Julio Apellido')
        ->assertSee('S/ 1,200.00')
        ->assertDontSee('<html', false);
});

// --------------------------------------------------------------- ajuste

it('ajusta bonificación y descuento y recalcula el total', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)
        ->postJson(route('admin.pagos.trabajadores.ajuste', $obligacion->id), ['bonificacion' => 150, 'descuento' => 50])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('total', 1000);

    $this->assertDatabaseHas('obligaciones_pago', [
        'id' => $obligacion->id,
        'bonificacion' => 150,
        'descuento' => 50,
        'monto_calculado' => 1000,
    ]);
});

it('rechaza un ajuste cuyo total quede en cero o negativo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)
        ->postJson(route('admin.pagos.trabajadores.ajuste', $obligacion->id), ['descuento' => 900])
        ->assertStatus(422);

    $this->assertDatabaseHas('obligaciones_pago', ['id' => $obligacion->id, 'monto_calculado' => 900]);
});

it('rechaza un ajuste con bonificación o descuento negativos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio');

    $this->actingAs($admin)
        ->postJson(route('admin.pagos.trabajadores.ajuste', $obligacion->id), ['bonificacion' => -10])
        ->assertStatus(422)
        ->assertJsonValidationErrors('bonificacion');
});

it('no permite ajustar un trabajador ya pagado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio');

    $this->actingAs($admin)->postJson(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
        'metodo_pago' => 'EFECTIVO',
        'fecha_pago' => '2026-09-20',
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.pagos.trabajadores.ajuste', $obligacion->id), ['bonificacion' => 50])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Este pago ya fue registrado. No se puede ajustar un pago ya realizado.');
});

// --------------------------------------------------------------- pagar

it('confirma el pago de un trabajador pendiente y lo deja seleccionado en la misma pantalla', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 1000);

    $this->actingAs($admin)
        ->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
            'metodo_pago' => 'EFECTIVO',
            'fecha_pago' => '2026-09-20',
            'observacion' => 'Pago en efectivo de prueba',
        ])
        ->assertRedirect(route('admin.pagos.trabajadores.index', ['trabajador' => $obligacion->id]));

    $obligacion->refresh();
    expect($obligacion->estado)->toBe('PAGADO');
    $this->assertDatabaseHas('pagos', ['obligacion_pago_id' => $obligacion->id, 'observacion' => 'Pago en efectivo de prueba']);
});

it('el panel cargado con filtros activos los conserva al confirmar el pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 1000);
    $rol = RoleModel::where('nombre', 'ACOPIADOR')->firstOrFail();

    // El JS pide el panel arrastrando los filtros activos de la URL (ver
    // admin-pago-trabajadores.js); el fragmento del formulario los guarda en
    // campos ocultos para que el POST de pago los conserve al redirigir.
    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', ['obligacion' => $obligacion->id, 'rol' => $rol->id, 'estado' => 'PENDIENTE']))
        ->assertOk()
        ->assertSee('name="rol" value="'.$rol->id.'"', false)
        ->assertSee('name="estado" value="PENDIENTE"', false);

    $this->actingAs($admin)
        ->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
            'metodo_pago' => 'EFECTIVO',
            'fecha_pago' => '2026-09-20',
            'rol' => $rol->id,
            'estado' => 'PENDIENTE',
        ])
        ->assertRedirect(route('admin.pagos.trabajadores.index', ['rol' => $rol->id, 'estado' => 'PENDIENTE', 'trabajador' => $obligacion->id]));
});

// --------------------------------------------------------- comprobante

it('imprime el comprobante individual de un trabajador pendiente o pagado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 1000);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertOk()
        ->assertSee('Julio Apellido')
        ->assertSee('Comprobante de pago')
        ->assertSeeInOrder(['Fecha de pago', '—', 'Total a pagar', 'Estado', 'Pendiente']);

    $this->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
        'metodo_pago' => 'EFECTIVO',
        'fecha_pago' => '2026-09-20',
    ]);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertOk()
        ->assertSeeInOrder(['Fecha de pago', '20/09/2026', 'Total pagado', 'Estado', 'Pagado']);
});

it('un trabajador pagado sigue visible en el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio');

    $this->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
        'metodo_pago' => 'EFECTIVO',
        'fecha_pago' => '2026-09-20',
    ]);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.index'))
        ->assertOk()
        ->assertSee('Julio Apellido')
        ->assertSee('Pagado');
});

// ----------------------------------------------------------- autorización

it('solo el administrador puede ver y pagar a trabajadores', function (string $rol) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio');
    $usuario = fixture_usuario($rol);

    $this->actingAs($usuario)->get(route('admin.pagos.trabajadores.index'))->assertForbidden();
    $this->actingAs($usuario)->get(route('admin.pagos.trabajadores.panel', $obligacion->id))->assertForbidden();
    $this->actingAs($usuario)->postJson(route('admin.pagos.trabajadores.ajuste', $obligacion->id), ['bonificacion' => 10])->assertForbidden();
    $this->actingAs($usuario)->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20'])->assertForbidden();

    $obligacion->refresh();
    expect($obligacion->estado)->toBe('PENDIENTE');
})->with(['ACOPIADOR', 'PROVEEDOR', 'CALIDAD']);

// ------------- huérfanos sin ficha real: role_id no basta para ser trabajador

/**
 * Inserta una obligación de personal PENDIENTE (o PAGADA) directamente, sin
 * pasar por GenerarObligacionesPersonal: simula una obligación que ya
 * existía en la base antes de esta corrección, por ejemplo la de un usuario
 * creado a mano con role_id de ACOPIADOR pero sin fila en `acopiadores`.
 */
function obligacion_personal_directa(User $usuario, float $monto = 1500.0, string $periodo = '2026-09-01', string $estado = 'PENDIENTE'): ObligacionPagoModel
{
    $periodoPago = PeriodoPago::mesQueContiene(new DateTimeImmutable($periodo));

    $creada = app(ObligacionPagoRepository::class)->guardarSiNoExiste(new ObligacionPago(
        id: null,
        tipoBeneficiario: TipoBeneficiarioPago::PERSONAL,
        proveedorId: null,
        userId: $usuario->id,
        rolId: $usuario->role_id,
        beneficiarioNombre: $usuario->username,
        configuracionPagoId: null,
        liquidacionId: null,
        periodoInicio: $periodoPago->inicio,
        periodoFin: $periodoPago->fin,
        periodicidad: PeriodicidadPago::MENSUAL,
        concepto: 'Sueldo mensual',
        baseCalculo: [],
        montoCalculado: $monto,
        estado: EstadoObligacionPago::from($estado),
        creadoPorUserId: null,
    ));

    return ObligacionPagoModel::findOrFail($creada->id());
}

it('CASO 1: un usuario con role_id de ACOPIADOR pero sin ficha real en `acopiadores` no aparece en Pagar a trabajadores ni genera obligación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_usuario('ACOPIADOR', 'acopiador.fake');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);

    visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertDontSee('acopiador.fake');

    expect(ObligacionPagoModel::where('tipo_beneficiario', 'PERSONAL')->count())->toBe(0);
});

it('CASO 2: un acopiador con ficha real activa y sueldo configurado sí aparece', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Rusman Torres', 1500);

    expect($obligacion->estado)->toBe('PENDIENTE');
    visitar_pagar_a_trabajadores($admin)->assertOk()->assertSee('Rusman Torres');
});

it('CASO 3: una obligación pendiente huérfana (usuario sin ficha real) se limpia de forma segura', function () {
    $huerfano = fixture_usuario('ACOPIADOR', 'acopiador.fake');
    $obligacion = obligacion_personal_directa($huerfano);

    $eliminadas = app(LimpiarObligacionesPersonalInvalidas::class)->execute();

    expect($eliminadas)->toBe(1);
    $this->assertDatabaseMissing('obligaciones_pago', ['id' => $obligacion->id]);
});

it('CASO 4: un pago PAGADO histórico nunca se elimina, aunque el usuario no tenga ficha real', function () {
    $huerfano = fixture_usuario('ACOPIADOR', 'acopiador.fake');
    $obligacion = obligacion_personal_directa($huerfano, periodo: '2026-08-01', estado: 'PAGADO');

    $eliminadas = app(LimpiarObligacionesPersonalInvalidas::class)->execute();

    expect($eliminadas)->toBe(0);
    $this->assertDatabaseHas('obligaciones_pago', ['id' => $obligacion->id, 'estado' => 'PAGADO']);
});

it('CASO 5: la vista ya no renderiza la columna Acciones ni botones de ver/imprimir por fila', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    trabajador_pendiente($admin, 'Julio', 1000);

    visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertDontSee('Acciones')
        ->assertDontSee('data-fila-trabajador-abrir', false);
});

it('CASO 6: la fila completa es clickeable y, al seleccionarla, el panel derecho carga ese trabajador', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Julio');
    pagos_personal('ACOPIADOR', 'Vanessa Huanca');
    pagos_configurar('ACOPIADOR', 1200, '2026-09-01', $admin);
    visitar_pagar_a_trabajadores($admin);

    $vanessa = ObligacionPagoModel::where('beneficiario_nombre', 'like', 'Vanessa Huanca%')->firstOrFail();

    visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertSee('data-fichas-fila', false)
        ->assertSee('data-ficha-id="'.$vanessa->id.'"', false)
        ->assertSee(e(route('admin.pagos.trabajadores.panel', $vanessa->id)), false);

    // Igual que hace el JS al hacer clic en la fila: pide el fragmento del panel para ese trabajador.
    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $vanessa->id))
        ->assertOk()
        ->assertSee('Vanessa Huanca');
});

it('CASO 7: un mes de 28 días (febrero) muestra el sueldo mensual completo, nunca un monto proporcional a los días del mes', function () {
    // 05:00 en Lima del 1 de febrero: ningún día de febrero cerró todavía (sin faltas).
    $this->travelTo('2026-02-01 10:00:00');
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Milkon', '2026-01-01');
    pagos_configurar('ACOPIADOR', 1500, '2026-01-01', $admin);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.index', ['periodo' => '2026-02']))
        ->assertOk()
        ->assertSee('Milkon')
        ->assertSee('S/ 1,500.00')
        ->assertDontSee('S/ 1,400.00');

    $obligacion = ObligacionPagoModel::where('beneficiario_nombre', 'like', 'Milkon%')->firstOrFail();
    expect($obligacion->periodo_inicio->format('Y-m-d'))->toBe('2026-02-01')
        ->and($obligacion->periodo_fin->format('Y-m-d'))->toBe('2026-02-28')
        ->and((float) $obligacion->acumulado)->toBe(1500.0);
});

// ------------------------------------------------------- rediseño: tabla y panel

it('la tabla muestra trabajador con DNI, rol legible y las columnas del cálculo real, sin columnas extra', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal('ACOPIADOR', 'Rusman Torres');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);

    $html = visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertSeeInOrder(['Finanzas', 'Pagos', 'Trabajadores', 'Pagos a trabajadores', 'Revisa los pagos mensuales del personal y registra la entrega correspondiente.'])
        ->assertSeeInOrder(['Trabajador', 'Rol', 'Asistencias', 'Faltas', 'Sueldo mensual', 'Valor/día', 'Acumulado', 'Total', 'Estado'])
        ->assertSeeInOrder(['Rusman Torres', 'DNI '.$persona->acopiador->dni, 'Acopiador', 'S/ 1,500.00', 'S/ 50.00'])
        ->getContent();

    // Sin botones por fila: el único "Imprimir comprobante" es el del panel derecho.
    expect($html)->not->toContain('>ACOPIADOR<')
        ->and(substr_count($html, 'data-imprimir-comprobante'))->toBe(1)
        ->and($html)->not->toContain('Generar reporte del mes');
});

it('el estado se muestra compacto (ícono + etiqueta) con su color propio: En curso, Pendiente y Pagado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $pendiente = trabajador_pendiente($admin, 'Julio', 900);
    pagos_personal('ACOPIADOR', 'Otro');
    visitar_pagar_a_trabajadores($admin);

    $html = visitar_pagar_a_trabajadores($admin)->getContent();
    expect($html)->toMatch('/class="pago-estado is-pendiente" data-pago-estado="PENDIENTE">\s*<svg[^>]*>.*?<\/svg>\s*<span>Pendiente<\/span>/s')
        ->toMatch('/class="pago-estado is-en-curso" data-pago-estado="EN_CURSO">\s*<svg[^>]*>.*?<\/svg>\s*<span>En curso<\/span>/s');

    $this->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $pendiente->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20']);

    expect(visitar_pagar_a_trabajadores($admin, ['estado' => 'PAGADO'])->getContent())
        ->toMatch('/class="pago-estado is-pagado" data-pago-estado="PAGADO">\s*<svg[^>]*>.*?<\/svg>\s*<span>Pagado<\/span>/s')
        ->not->toContain('data-pago-estado="EN_CURSO"');
});

it('las faltas de la tabla solo cuentan días en que el trabajador estaba activo, igual que el calendario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal('ACOPIADOR', 'Yanil', '2026-09-15');
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    visitar_pagar_a_trabajadores($admin);
    $identidad = pagos_trabajador($persona);

    // Falta vieja antes de su ingreso: no le correspondía, no debe contarse.
    pagos_falta($identidad->id, $persona->role_id, '2026-09-05');
    $obligacion = ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail();

    // 05:00 del 20 en Lima: cerrados del 15 al 19 sin asistencia => 5 faltas válidas.
    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $obligacion->id))
        ->assertOk()
        ->assertSeeInOrder(['Detalle del pago', 'Asistencias', '0 días', 'Faltas', '5 días']);
});

it('el panel es la vista previa del comprobante; pendiente permite imprimir y registrar pago, en curso solo imprimir', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $pendiente = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $pendiente->id))
        ->assertOk()
        ->assertSeeInOrder(['Comprobante del trabajador', 'Vista previa del comprobante del trabajador seleccionado.'])
        ->assertSeeInOrder(['Comprobante de pago', 'Periodo', 'Septiembre 2026', 'Datos del trabajador', 'Julio Apellido', 'Acopiador', 'Detalle del pago', 'Sueldo mensual', 'S/ 900.00', 'Valor por día', 'S/ 30.00', 'Asistencias', 'Faltas', 'Acumulado', 'Bonificaciones', 'Descuentos', 'Total a pagar', 'S/ 900.00', 'Estado', 'Pendiente'])
        ->assertSeeInOrder(['Imprimir comprobante', 'Registrar pago'])
        ->assertSee('data-form-confirmar-pago', false);

    pagos_personal('ACOPIADOR', 'Otro');
    visitar_pagar_a_trabajadores($admin);
    $enCurso = ObligacionPagoModel::where('beneficiario_nombre', 'like', 'Otro%')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $enCurso->id))
        ->assertOk()
        ->assertSee('El mes aún está en curso')
        ->assertSee('Imprimir comprobante')
        ->assertDontSee('data-registrar-pago', false)
        ->assertDontSee('data-form-confirmar-pago', false);
});

it('el listado no hace consultas por fila (sin N+1) aunque crezcan los trabajadores', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    pagos_personal('ACOPIADOR', 'Uno');
    visitar_pagar_a_trabajadores($admin);

    $contarConsultas = function (): int {
        $filtro = TrabajadoresPagoFiltro::resolver(periodo: '2026-09');
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TrabajadoresPagoListadoQuery::class)->obtener($filtro)->items();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $conUno = $contarConsultas();

    foreach (range(1, 5) as $i) {
        pagos_personal('ACOPIADOR', 'Persona '.$i);
    }
    visitar_pagar_a_trabajadores($admin);

    expect($contarConsultas())->toBe($conUno);
});

// ------------------------------------------------------- comprobante tipo factura

it('el comprobante de un mes en curso muestra número, emisión, periodo, datos del trabajador, detalle y total a pagar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Faon');
    $persona = pagos_personal('ACOPIADOR', 'Rusman Torres', '2026-09-14');
    $persona->acopiador->update(['zona_id' => $zona->id]);
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);
    visitar_pagar_a_trabajadores($admin);
    $obligacion = ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail();
    $obligacion->refresh();

    $html = $this->actingAs($admin)->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertOk()
        ->assertSeeInOrder(['EcoLact', 'Comprobante de pago', 'N.° P-2026-'.str_pad((string) $obligacion->id, 4, '0', STR_PAD_LEFT), 'Fecha de emisión', '20/09/2026', 'Periodo', 'Septiembre 2026', 'Fecha de pago', '—'])
        ->assertSeeInOrder(['Datos del trabajador', 'Nombre', 'Rusman Torres', 'DNI', $persona->acopiador->dni, 'Rol', 'Acopiador', 'Fecha de ingreso', '14/09/2026'])
        ->assertSeeInOrder(['Detalle del pago', 'Sueldo mensual', 'S/ 1,500.00', 'Valor por día', 'S/ 50.00', 'Asistencias', 'Faltas', 'Acumulado', 'Bonificaciones', 'Descuentos', 'Total a pagar', 'S/ '.number_format((float) $obligacion->monto_calculado, 2)])
        ->assertSeeInOrder(['Estado', 'En curso', 'El mes aún no ha cerrado'])
        // Sin reglas internas ni datos de configuración dentro del documento.
        ->assertDontSee('Configuración de pagos')
        ->assertDontSee('÷ 30')
        ->getContent();

    // Documento, no dashboard: sin sidebar, avatar, badges, QR ni botones dentro de la hoja.
    $hoja = substr($html, strpos($html, '<article class="cp-hoja"'));
    expect($html)->not->toContain('data-sidebar')
        ->and($hoja)->not->toContain('<button')
        ->not->toContain('data-qr-code')
        ->not->toContain('pago-estado')
        ->not->toContain('avatar')
        // Sin logo gráfico: ni imagen ni SVG en la hoja.
        ->not->toContain('<img')
        ->not->toContain('<svg');
});

it('el comprobante pagado muestra bonificación, descuento, fecha de pago y un total que cuadra con el acumulado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)->postJson(route('admin.pagos.trabajadores.ajuste', $obligacion->id), ['bonificacion' => 150, 'descuento' => 50])->assertOk();
    $this->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20']);

    $this->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertOk()
        ->assertSeeInOrder(['Fecha de emisión', '20/09/2026', 'Periodo', 'Septiembre 2026', 'Fecha de pago', '20/09/2026'])
        ->assertSeeInOrder(['Acumulado', 'S/ 900.00', 'Bonificaciones', '+ S/ 150.00', 'Descuentos', '− S/ 50.00', 'Total pagado', 'S/ 1,000.00'])
        ->assertSeeInOrder(['Estado', 'Pagado', 'Método de pago', 'Efectivo', 'Registrado por', $admin->username]);
});

it('el comprobante es un documento formal sin logo: cabecera de texto, número y firmas del trabajador y autorizada al pie', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);
    $numero = 'P-2026-'.str_pad((string) $obligacion->id, 4, '0', STR_PAD_LEFT);

    $html = $this->actingAs($admin)->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertOk()
        ->assertSeeInOrder(['EcoLact', 'Comprobante de pago', 'N.° '.$numero])
        ->assertSeeInOrder(['Firma del trabajador', 'Julio Apellido', 'Firma autorizada', 'EcoLact'])
        ->assertDontSee('Gestión integral')
        ->assertDontSee('Vista previa - pago aún no registrado')
        ->getContent();

    expect($html)->not->toContain('cp-empresa')
        ->not->toContain('logo');
});

it('la impresión es una sola hoja A4 vertical: sin márgenes de navegador, sin barra y sin saltos de página', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertSee('@page { size: A4 portrait; margin: 0; }', false)
        ->assertSee('data-comprobante-toolbar', false);

    $css = file_get_contents(resource_path('css/components/comprobante-pago.css'));
    $print = substr($css, strpos($css, '@media print'));
    expect($css)->toContain('width: 210mm; height: 297mm;')
        ->and($print)->toContain('.cp-toolbar { display: none !important; }')
        ->toContain('.cp-hoja { height: 296mm; margin: 0; break-after: avoid;')
        ->toContain('.cp-seccion, .cp-total, .cp-firmas { break-inside: avoid;');

    // Imprimir desde la pantalla (Ctrl+P) deja solo la hoja: sin sidebar, filtros, tabla ni botones.
    $pantalla = file_get_contents(resource_path('css/pages/admin-pagos-trabajadores.css'));
    expect(substr($pantalla, strpos($pantalla, '@media print')))
        ->toContain('body.pagos-trabajadores-page * { visibility: hidden !important; }')
        ->toContain('.pt-escala .cp-hoja * { visibility: visible !important; }');
});

it('un pago pendiente ofrece imprimir y registrar pago; uno pagado solo imprimir, nunca registrarlo otra vez', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);
    $url = e(route('admin.pagos.trabajadores.comprobante', $obligacion->id));

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $obligacion->id))
        ->assertSee('href="'.$url.'"', false)
        ->assertSee('data-imprimir-comprobante', false)
        ->assertSee('data-registrar-pago', false);

    $this->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20']);

    $this->get(route('admin.pagos.trabajadores.panel', $obligacion->id))
        ->assertSee('Imprimir comprobante')
        ->assertSee('Pagado el 20/09/2026')
        ->assertDontSee('data-registrar-pago', false)
        ->assertDontSee('data-form-confirmar-pago', false);
});

// ------------------------------------------- todos los trabajadores se ven (sin sueldo)

it('todos los trabajadores del mes se ven: con sueldo configurado su pago, y sin sueldo marcados como "Sin configurar"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);
    pagos_personal('ACOPIADOR', 'Vanessa Huanca');
    pagos_personal('ENCARGADO_PLANTA', 'Abel Quispe');
    pagos_personal('VENTAS', 'Elena Ramos');
    pagos_personal('CALIDAD', 'David Lopez');

    visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertSeeInOrder(['Vanessa Huanca', 'Acopiador', 'S/ 1,500.00', 'En curso'])
        ->assertSeeInOrder(['Abel Quispe', 'Encargado de planta', 'Sin configurar'])
        ->assertSeeInOrder(['David Lopez', 'Analista de calidad', 'Sin configurar'])
        ->assertSeeInOrder(['Elena Ramos', 'Personal de ventas', 'Sin configurar'])
        ->assertSee('Los trabajadores sin sueldo configurado se muestran sin monto.');

    // No se inventa dinero: solo el rol con sueldo tiene obligación.
    expect(ObligacionPagoModel::count())->toBe(1);

    visitar_pagar_a_trabajadores($admin, ['estado' => 'SIN_SUELDO'])
        ->assertSee('Abel Quispe')->assertSee('Elena Ramos')->assertDontSee('Vanessa Huanca');
    visitar_pagar_a_trabajadores($admin, ['estado' => 'EN_CURSO'])
        ->assertSee('Vanessa Huanca')->assertDontSee('Abel Quispe');
});

it('el panel de un trabajador sin sueldo dice "Pago no disponible" e indica dónde se configura, sin botones ni enlaces para configurar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $abel = pagos_personal('ENCARGADO_PLANTA', 'Abel Quispe');
    visitar_pagar_a_trabajadores($admin);
    $trabajador = pagos_trabajador($abel);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.sin-sueldo', ['trabajador' => $trabajador->id, 'periodo' => '2026-09']))
        ->assertOk()
        ->assertSeeInOrder(['Abel Quispe', 'Encargado de planta', 'Pago no disponible', 'No existe un sueldo vigente para el rol de este trabajador', 'Configúrala desde: Configuración &gt; Configuración de pagos.'], false)
        ->assertDontSee(route('admin.configuracion-pagos.index'), false)
        ->assertDontSee('Configurar sueldo')
        ->assertDontSee('<a ', false)
        ->assertDontSee('data-form-confirmar-pago', false)
        ->assertDontSee('data-imprimir-comprobante', false);

    visitar_pagar_a_trabajadores($admin, ['trabajador' => 's'.$trabajador->id])
        ->assertSee('data-ficha-id="s'.$trabajador->id.'"', false)
        ->assertSee('Pago no disponible');
});

// ------------------------------------------- rediseño: comprobante a la derecha

it('el sueldo es por rol: el personal de un rol sin sueldo se ve como Sin configurar, aunque otro rol tenga sueldo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('VENTAS', 'David Lopez');
    $ana = pagos_personal('CALIDAD', 'Ana Rojas');
    pagos_configurar('CALIDAD', 1800, '2026-09-01', $admin);

    visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertSeeInOrder(['Ana Rojas', 'Analista de calidad', 'S/ 1,800.00', 'S/ 60.00'])
        ->assertSeeInOrder(['David Lopez', 'Personal de ventas', 'Sin configurar']);

    expect(ObligacionPagoModel::where('user_id', $ana->id)->count())->toBe(1)
        ->and(ObligacionPagoModel::count())->toBe(1);
});

it('la franja de métricas resume trabajadores, pendientes, pagados y total pendiente con los mismos filtros del listado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    trabajador_pendiente($admin, 'Julio', 900);
    pagos_personal('ACOPIADOR', 'Otro');
    pagos_personal('CALIDAD', 'David Lopez');
    visitar_pagar_a_trabajadores($admin)->assertOk()->assertSee('Total pendiente');

    $resumen = fn (array $filtros = []) => app(TrabajadoresPagoListadoQuery::class)
        ->resumen(TrabajadoresPagoFiltro::resolver(...['periodo' => '2026-09'] + $filtros));

    // Trabajadores = las mismas filas del listado (incluye a los "Sin configurar", p. ej. el administrador).
    $filas = app(TrabajadoresPagoListadoQuery::class)->obtener(TrabajadoresPagoFiltro::resolver(periodo: '2026-09'));
    expect($filas->total())->toBeGreaterThanOrEqual(3);

    expect($resumen())
        ->trabajadores->toBe($filas->total())
        ->pendientes->toBe(1)
        ->pagados->toBe(0)
        ->totalPendiente->toBe(900.0)
        ->and($resumen(['estado' => 'EN_CURSO']))
        ->trabajadores->toBe(1)
        ->pendientes->toBe(0);
});

it('Registrar pago abre un modal de confirmación con trabajador, periodo, total y Efectivo por defecto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $obligacion->id))
        ->assertOk()
        ->assertSee('data-modal-open="modal-registrar-pago"', false)
        ->assertSee('id="modal-registrar-pago"', false)
        ->assertSeeInOrder(['Registrar pago', 'Trabajador', 'Julio Apellido', 'Periodo', 'Septiembre 2026', 'Total', 'S/ 900.00'])
        ->assertSee('<option value="EFECTIVO" selected>Efectivo</option>', false)
        ->assertSee('name="monto" value="900.00"', false)
        ->assertSee('name="fecha_pago" type="date" required max="2026-09-20"', false)
        ->assertSeeInOrder(['Observación', 'Cancelar', 'Confirmar pago']);
});

it('no registra el pago si el total confirmado ya no coincide con el calculado (comprobante desactualizado)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)
        ->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20', 'monto' => '800.00'])
        ->assertSessionHasErrors('pago');

    expect($obligacion->fresh()->estado)->toBe('PENDIENTE');
    $this->assertDatabaseCount('pagos', 0);
});

it('el pago congela lo que mostró el comprobante: una corrección de asistencia posterior no cambia sus faltas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);
    $faltasAlPagar = app(TrabajadoresPagoListadoQuery::class)->buscarFila($obligacion->id)->faltas;
    expect($faltasAlPagar)->toBeGreaterThan(0);

    $this->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20'])
        ->assertSessionHasNoErrors();

    expect(DB::table('pagos')->where('obligacion_pago_id', $obligacion->id)->value('comprobante_snapshot'))
        ->json()->faltas->toBe($faltasAlPagar)->sueldo_mensual->toEqual(900)->total->toEqual(900);

    // Corrección de asistencia después del pago: las faltas del mes pasan a "presente".
    DB::table('jornadas_personal')->update(['estado' => 'PRESENTE']);

    $this->get(route('admin.pagos.trabajadores.panel', $obligacion->id))
        ->assertSeeInOrder(['Faltas', $faltasAlPagar.' días', 'Total pagado', 'S/ 900.00']);
});
