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

it('CASO 3: un rol remunerable sin sueldo configurado no genera obligación, pero el trabajador igual se muestra "Sin sueldo"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('CALIDAD', 'David Lopez');

    $response = visitar_pagar_a_trabajadores($admin);

    $response->assertOk()
        ->assertSeeInOrder(['David Lopez', 'Analista de calidad', 'Sin sueldo'])
        ->assertSee('No hay sueldos configurados para este periodo.');

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

it('sin trabajadores activos para el rol configurado muestra el mensaje correspondiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);

    visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertSee('No hay trabajadores activos para las configuraciones disponibles.');
});

// ----------------------------------------------------------------- panel

it('el panel de un trabajador se sirve sin layout con su resumen de pago', function () {
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
        ->assertSee('Vista previa');

    $this->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), [
        'metodo_pago' => 'EFECTIVO',
        'fecha_pago' => '2026-09-20',
    ]);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertOk()
        ->assertSeeInOrder(['Fecha de pago', '20/09/2026'])
        ->assertDontSee('Vista previa');
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
        ->assertSeeInOrder(['Finanzas', 'Pagos', 'Trabajadores', 'Pagos a trabajadores', 'Gestiona los pagos individuales de los trabajadores correspondientes al mes seleccionado.'])
        ->assertSeeInOrder(['Trabajador', 'Rol', 'Asistencias', 'Faltas', 'Sueldo mensual', 'Valor día', 'Acumulado', 'Total', 'Estado'])
        ->assertSeeInOrder(['Rusman Torres', 'DNI '.$persona->acopiador->dni, 'Acopiador', 'S/ 1,500.00', 'S/ 50.00'])
        ->getContent();

    expect($html)->not->toContain('>ACOPIADOR<')
        ->not->toContain('Imprimir comprobante')
        ->not->toContain('Generar reporte del mes');
});

it('el estado se muestra con su etiqueta y color propio: En curso, Pendiente y Pagado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $pendiente = trabajador_pendiente($admin, 'Julio', 900);
    pagos_personal('ACOPIADOR', 'Otro');
    visitar_pagar_a_trabajadores($admin);

    $html = visitar_pagar_a_trabajadores($admin)->getContent();
    expect($html)->toContain('class="pago-estado is-pendiente" data-pago-estado="PENDIENTE">Pendiente</span>')
        ->toContain('class="pago-estado is-en-curso" data-pago-estado="EN_CURSO">En curso</span>');

    $this->actingAs($admin)->post(route('admin.pagos.trabajadores.pagar', $pendiente->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20']);

    expect(visitar_pagar_a_trabajadores($admin, ['estado' => 'PAGADO'])->getContent())
        ->toContain('class="pago-estado is-pagado" data-pago-estado="PAGADO">Pagado</span>')
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
        ->assertSeeInOrder(['Resumen de asistencia', 'Asistencias', '0', 'Faltas', '5']);
});

it('el panel resume asistencia y detalle de pago; pendiente permite registrar pago y en curso no', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $pendiente = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $pendiente->id))
        ->assertOk()
        ->assertSeeInOrder(['Julio Apellido', 'Acopiador', 'Periodo', 'Septiembre 2026', 'Resumen de asistencia', 'Asistencias', 'Faltas', 'Detalle de pago', 'Sueldo mensual', 'S/ 900.00', 'Valor por día', 'S/ 30.00', 'Acumulado', 'Bonificaciones', 'Descuentos', 'Total final', 'S/ 900.00', 'Estado del pago', 'Pendiente'])
        ->assertSee('data-form-confirmar-pago', false)
        ->assertSee('Registrar pago')
        ->assertDontSee('Imprimir comprobante');

    pagos_personal('ACOPIADOR', 'Otro');
    visitar_pagar_a_trabajadores($admin);
    $enCurso = ObligacionPagoModel::where('beneficiario_nombre', 'like', 'Otro%')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $enCurso->id))
        ->assertOk()
        ->assertSee('El mes aún no ha sido cerrado')
        ->assertDontSee('data-form-confirmar-pago', false)
        ->assertDontSee('Imprimir comprobante');
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

it('el comprobante de un mes en curso es una vista previa con número, emisión, periodo, datos del trabajador y total a pagar', function () {
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
        ->assertSeeInOrder(['Comprobante de pago', 'N.° P-2026-'.str_pad((string) $obligacion->id, 4, '0', STR_PAD_LEFT), 'Vista previa', 'Fecha de emisión', '20/09/2026', 'Periodo', 'Septiembre 2026', 'Total a pagar', 'S/ '.number_format((float) $obligacion->monto_calculado, 2)])
        ->assertSeeInOrder(['Rusman Torres', 'DNI', $persona->acopiador->dni, 'Rol', 'Acopiador', 'Zona', 'Faon', 'Fecha de ingreso', '14/09/2026'])
        ->assertSeeInOrder(['Concepto', 'Detalle', 'Cantidad', 'Monto (S/)', 'Sueldo mensual', 'S/ 1,500.00', 'Valor por día', 'S/ 50.00', 'Asistencias', 'Faltas', 'Acumulado del periodo', 'Bonificaciones', 'Descuentos'])
        ->assertSeeInOrder(['Subtotal', 'Bonificaciones', 'Descuentos', 'Total'])
        ->assertDontSee('Fecha de pago')
        ->getContent();

    // Documento, no dashboard: sin sidebar, avatar, badges, QR ni botones dentro de la hoja.
    $hoja = substr($html, strpos($html, '<article class="cp-hoja"'));
    expect($html)->not->toContain('data-sidebar')
        ->and($hoja)->not->toContain('<button')
        ->not->toContain('data-qr-code')
        ->not->toContain('pago-estado')
        ->not->toContain('avatar');
});

it('el comprobante pagado muestra bonificación, descuento, fecha de pago y un total que cuadra con el acumulado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);

    $this->actingAs($admin)->postJson(route('admin.pagos.trabajadores.ajuste', $obligacion->id), ['bonificacion' => 150, 'descuento' => 50])->assertOk();
    $this->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20']);

    $this->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertOk()
        ->assertDontSee('Vista previa')
        ->assertSeeInOrder(['Fecha de emisión', '20/09/2026', 'Periodo', 'Septiembre 2026', 'Fecha de pago', '20/09/2026', 'Total pagado', 'S/ 1,000.00'])
        ->assertSeeInOrder(['Acumulado del periodo', 'S/ 900.00', 'Bonificaciones', 'S/ 150.00', 'Descuentos', '− S/ 50.00'])
        ->assertSeeInOrder(['Subtotal', 'S/ 900.00', 'Bonificaciones', 'S/ 150.00', 'Descuentos', '− S/ 50.00', 'Total', 'S/ 1,000.00'])
        ->assertSeeInOrder(['Método de pago', 'Efectivo']);
});

it('el comprobante es un documento sobrio: cabecera con fecha/hora e identificador, sin bloque corporativo, con firmas al pie', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);
    $numero = 'P-2026-'.str_pad((string) $obligacion->id, 4, '0', STR_PAD_LEFT);

    $html = $this->actingAs($admin)->get(route('admin.pagos.trabajadores.comprobante', $obligacion->id))
        ->assertOk()
        ->assertSeeInOrder(['20/09/2026, 05:00', 'Comprobante '.$numero.' - Julio Apellido', 'Comprobante de pago', 'N.° '.$numero])
        ->assertSee('Vista previa - pago aún no registrado')
        ->assertDontSee('mes en curso: montos parciales')
        ->assertSeeInOrder(['Trabajador', 'Julio Apellido', 'DNI', 'Rol', 'Zona', 'Fecha de ingreso', 'Periodo'])
        ->assertSeeInOrder(['Recibí conforme - Julio Apellido', 'Entregado por - EcoLact'])
        ->assertDontSee('Gestión integral')
        ->getContent();

    expect($html)->not->toContain('cp-empresa');
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
        ->toContain('.cp-tabla tr, .cp-cierre, .cp-firmas { break-inside: avoid;');
});

it('el panel ofrece imprimir el comprobante pagado y, antes del pago, solo la vista previa', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = trabajador_pendiente($admin, 'Julio', 900);
    $url = e(route('admin.pagos.trabajadores.comprobante', $obligacion->id));

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.panel', $obligacion->id))
        ->assertSee('Imprimir vista previa')
        ->assertDontSee('Imprimir comprobante')
        ->assertSee('href="'.$url.'"', false);

    $this->post(route('admin.pagos.trabajadores.pagar', $obligacion->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20']);

    $this->get(route('admin.pagos.trabajadores.panel', $obligacion->id))
        ->assertSee('Imprimir comprobante')
        ->assertSee('data-imprimir-comprobante', false);
});

// ------------------------------------------- todos los trabajadores se ven (sin sueldo)

it('todos los trabajadores del mes se ven: con sueldo configurado su pago, y sin sueldo marcados como "Sin sueldo"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);
    pagos_personal('ACOPIADOR', 'Vanessa Huanca');
    pagos_personal('ENCARGADO_PLANTA', 'Abel Quispe');
    pagos_personal('VENTAS', 'Elena Ramos');
    pagos_personal('CALIDAD', 'David Lopez');

    visitar_pagar_a_trabajadores($admin)
        ->assertOk()
        ->assertSeeInOrder(['Vanessa Huanca', 'Acopiador', 'S/ 1,500.00', 'En curso'])
        ->assertSeeInOrder(['Abel Quispe', 'Encargado de planta', 'Sin sueldo'])
        ->assertSeeInOrder(['David Lopez', 'Analista de calidad', 'Sin sueldo'])
        ->assertSeeInOrder(['Elena Ramos', 'Personal de ventas', 'Sin sueldo']);

    // No se inventa dinero: solo el rol con sueldo tiene obligación.
    expect(ObligacionPagoModel::count())->toBe(1);

    visitar_pagar_a_trabajadores($admin, ['estado' => 'SIN_SUELDO'])
        ->assertSee('Abel Quispe')->assertSee('Elena Ramos')->assertDontSee('Vanessa Huanca');
    visitar_pagar_a_trabajadores($admin, ['estado' => 'EN_CURSO'])
        ->assertSee('Vanessa Huanca')->assertDontSee('Abel Quispe');
});

it('el panel de un trabajador sin sueldo explica cómo configurarlo y no permite pagar ni imprimir', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $abel = pagos_personal('ENCARGADO_PLANTA', 'Abel Quispe');
    visitar_pagar_a_trabajadores($admin);
    $trabajador = pagos_trabajador($abel);

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.sin-sueldo', ['trabajador' => $trabajador->id, 'periodo' => '2026-09']))
        ->assertOk()
        ->assertSeeInOrder(['Abel Quispe', 'Encargado de planta', 'Resumen de asistencia', 'Estado del pago', 'Sin sueldo'])
        ->assertSee('no tiene sueldo configurado')
        ->assertSee(route('admin.configuracion-pagos.index'), false)
        ->assertDontSee('data-form-confirmar-pago', false)
        ->assertDontSee('data-imprimir-comprobante', false);

    visitar_pagar_a_trabajadores($admin, ['trabajador' => 's'.$trabajador->id])
        ->assertSee('data-ficha-id="s'.$trabajador->id.'"', false)
        ->assertSee('no tiene sueldo configurado');
});
