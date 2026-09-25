<?php

use App\Application\Pago\GenerarPagosPendientes;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ReglaPagoCalidadModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/**
 * Genera la ventana de personal y la fuerza a PENDIENTE (30/30 días) sin
 * pasar por jornadas reales: ninguno de estos tests vuelve a llamar a
 * GenerarPagosPendientes antes de pagar, así que no hay riesgo de que una
 * recalculación posterior la devuelva a EN_CURSO.
 */
function pendiente_de_acopiador(User $admin): ObligacionPagoModel
{
    pagos_personal('ACOPIADOR', 'Julio');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    $obligacion = ObligacionPagoModel::firstOrFail();
    $obligacion->update([
        'dias_trabajados' => 30,
        'valor_dia' => round(1000 / 30, 2),
        'acumulado' => 1000,
        'monto_calculado' => 1000,
        'estado' => 'PENDIENTE',
    ]);

    return $obligacion->fresh();
}

// --------------------------------------------------------------------------
// Menú
// --------------------------------------------------------------------------

it('el menú del administrador tiene Configuración de pagos y el grupo Pagos, sin Liquidaciones', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $html = $this->actingAs($admin)->get(route('admin.configuracion-pagos.index'))->assertOk()->getContent();

    expect($html)->toContain('Configuración de pagos')
        ->and($html)->toContain(route('admin.pagos.proveedores.index'))
        ->and($html)->toContain(route('admin.pagos.trabajadores.index'))
        ->and($html)->not->toContain('>Liquidaciones<')
        ->and($html)->not->toContain('>Reglas de pago<');
});

// --------------------------------------------------------------------------
// Configuración de pagos
// --------------------------------------------------------------------------

it('la pantalla de configuración muestra KPIs, cómo funciona y las dos secciones', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_precio_leche(2.10, new DateTimeImmutable('2026-01-01'), $admin->id);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $admin);
    pagos_configurar('ACOPIADOR', 1000, '2026-01-01', $admin);

    $this->actingAs($admin)->get(route('admin.configuracion-pagos.index'))
        ->assertOk()
        ->assertSeeInOrder(['Configuración de pagos', 'Configuraciones activas', 'Pagos a proveedores', 'Pagos al personal', '¿Cómo funciona?'])
        ->assertSee('Define cómo se calculan los pagos de proveedores y personal. Esta configuración genera pagos pendientes en el módulo Pagos.')
        ->assertSee('Configuras cómo se calcula el pago.')
        ->assertSee('El sistema genera el pago pendiente según el periodo.')
        ->assertSee('En Pagos confirmas la entrega del dinero.')
        ->assertSeeInOrder(['Tipo', 'Cálculo', 'Precio base', 'Ajuste por calidad', 'Periodicidad', 'Estado', 'Acción'])
        ->assertSee('El precio por litro se configura aquí. Los ajustes de calidad se aplican solo si están activados.')
        ->assertSee('S/ 2.10')
        ->assertSee('por litro')
        ->assertSee('Configura el sueldo de cada rol del personal. Los roles se cargan desde la tabla roles del sistema.')
        ->assertSee('Sueldo fijo mensual')
        ->assertSee('S/ 1,000.00')
        ->assertSee('Nueva configuración de pago')
        ->assertSee('Define cómo se calculará el pago del proveedor y cómo luego aparecerá en el módulo Pagos.')
        ->assertSee('Configura el pago para proveedores de leche.')
        ->assertSee('Configura el sueldo para el personal de la planta.')
        ->assertSee('Precio base por litro (S/)')
        ->assertSee('Aplicar ajustes de calidad')
        ->assertSee('El precio base de la leche ahora se configura aquí; no existe un módulo separado de Precio de leche.')
        ->assertSee('El valor diario se calcula automáticamente dividiendo el sueldo mensual entre 30. Los trabajadores que cumplen todo el mes reciben su sueldo mensual completo.');
});

it('la tabla de personal se arma con los roles remunerables de la base de datos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_rol('PROVEEDOR');
    RoleModel::create(['nombre' => 'ALMACENERO', 'descripcion' => null, 'es_remunerable' => true]);

    $html = $this->actingAs($admin)->get(route('admin.configuracion-pagos.index'))->assertOk()->assertSee('ALMACENERO')->getContent();

    $tabla = substr($html, strpos($html, 'Pagos al personal'));

    expect($tabla)->toContain('ADMINISTRADOR')->toContain('ALMACENERO')
        ->and(substr_count($tabla, '>PROVEEDOR<'))->toBe(0);
});

it('el modal de ajustes por calidad muestra Aceptada, Observada y Rechazada como no pagable', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);

    $this->actingAs($admin)->get(route('admin.configuracion-pagos.index'))
        ->assertOk()
        ->assertSee('Ajustes por calidad')
        ->assertSeeInOrder(['Resultado', 'Tipo de ajuste', 'Valor', 'Aceptada', 'Observada', 'Rechazada', 'No pagable'])
        ->assertSee('value="-0.30"', false)
        ->assertDontSee('Ajustes de calidad del precio del proveedor');
});

it('no se siembran sueldos: sin configurar, ningún rol muestra un monto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.configuracion-pagos.index'))
        ->assertOk()
        ->assertSee('Sin configurar');

    expect(ConfiguracionPagoModel::count())->toBe(0);
});

it('guarda una configuración de personal sin registrar dinero ni crear pagos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_rol('CALIDAD');

    $this->actingAs($admin)->post(route('admin.configuracion-pagos.store'), [
        'tipo_beneficiario' => 'PERSONAL',
        'rol_id' => fixture_rol('CALIDAD')->id,
        'tipo_calculo' => 'SUELDO_FIJO',
        'periodicidad' => 'MENSUAL',
        'monto_base' => '1200.00',
        'fecha_inicio' => '2026-10-01',
        'observacion' => 'Sueldo vigente desde octubre.',
    ])->assertRedirect(route('admin.configuracion-pagos.index'))->assertSessionHas('success');

    $configuracion = ConfiguracionPagoModel::firstOrFail();

    expect((float) $configuracion->monto_base)->toBe(1200.0)
        ->and($configuracion->observacion)->toBe('Sueldo vigente desde octubre.')
        ->and($configuracion->creado_por_user_id)->toBe($admin->id)
        ->and(ObligacionPagoModel::count())->toBe(0)
        ->and(PagoModel::count())->toBe(0);
});

it('guarda una configuración de proveedor con precio base y ajustes de calidad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->post(route('admin.configuracion-pagos.store'), [
        'tipo_beneficiario' => 'PROVEEDOR',
        'periodicidad' => 'SEMANAL',
        'precio_base_litro' => '1.70',
        'aplica_calidad' => '1',
        'ajustes' => ['ACEPTADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '0'], 'OBSERVADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '-0.10']],
        'fecha_inicio' => '2026-09-01',
    ])->assertRedirect(route('admin.configuracion-pagos.index'));

    $configuracion = ConfiguracionPagoModel::firstOrFail();

    expect($configuracion->tipo_calculo)->toBe('POR_LITROS')
        ->and($configuracion->monto_base)->toBeNull()
        ->and($configuracion->aplica_calidad)->toBeTrue()
        ->and((float) PrecioLecheModel::firstOrFail()->precio_litro)->toBe(1.7)
        ->and((float) ReglaPagoCalidadModel::where('resultado_calidad', 'OBSERVADA')->value('valor_ajuste'))->toBe(-0.1);
});

it('valida sueldo, fecha de inicio y rol al guardar una configuración', function (array $datos, string $campo) {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_rol('ACOPIADOR');

    $base = ['tipo_beneficiario' => 'PERSONAL', 'rol_id' => fixture_rol('ACOPIADOR')->id, 'monto_base' => '1000', 'fecha_inicio' => '2026-10-01'];

    $this->actingAs($admin)->from(route('admin.configuracion-pagos.index'))
        ->post(route('admin.configuracion-pagos.store'), array_merge($base, $datos))
        ->assertSessionHasErrors($campo);

    expect(ConfiguracionPagoModel::count())->toBe(0);
})->with([
    'sueldo cero' => [['monto_base' => '0'], 'monto_base'],
    'sueldo negativo' => [['monto_base' => '-10'], 'monto_base'],
    'sueldo vacío' => [['monto_base' => ''], 'monto_base'],
    'sueldo con tres decimales' => [['monto_base' => '1000.123'], 'monto_base'],
    'sueldo no numérico' => [['monto_base' => 'mil'], 'monto_base'],
    'sin fecha de inicio' => [['fecha_inicio' => ''], 'fecha_inicio'],
    'fecha inválida' => [['fecha_inicio' => 'no-es-fecha'], 'fecha_inicio'],
    'sin rol' => [['rol_id' => ''], 'rol_id'],
    'rol inexistente' => [['rol_id' => 9999], 'rol_id'],
    'rol que no es remunerable' => [['rol_id' => 'PROVEEDOR'], 'rol_id'],
    'acopiador por litros' => [['tipo_calculo' => 'POR_LITROS'], 'tipo_calculo'],
    'periodicidad semanal para personal' => [['periodicidad' => 'SEMANAL'], 'periodicidad'],
]);

it('rechaza una configuración cuya vigencia se cruza con otra del mismo rol', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);

    $this->actingAs($admin)->from(route('admin.configuracion-pagos.index'))->post(route('admin.configuracion-pagos.store'), [
        'tipo_beneficiario' => 'PERSONAL', 'rol_id' => fixture_rol('ACOPIADOR')->id, 'monto_base' => '1200', 'fecha_inicio' => '2026-10-01',
    ])->assertSessionHasErrors('configuracion');

    expect(ConfiguracionPagoModel::count())->toBe(1);
});

it('editar un sueldo con pagos generados abre una nueva vigencia', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pendiente_de_acopiador($admin);
    $configuracion = ConfiguracionPagoModel::firstOrFail();

    $this->actingAs($admin)->patch(route('admin.configuracion-pagos.update', $configuracion), [
        'monto_base' => '1100', 'fecha_inicio' => '2026-10-01',
    ])->assertRedirect(route('admin.configuracion-pagos.index'))->assertSessionHas('success');

    expect(ConfiguracionPagoModel::count())->toBe(2)
        ->and((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(1000.0);
});

it('solo el administrador puede ver o cambiar configuraciones y pagos', function (string $rol) {
    $usuario = fixture_usuario($rol);
    $obligacion = pendiente_de_acopiador(fixture_usuario('ADMINISTRADOR'));
    $configuracion = ConfiguracionPagoModel::firstOrFail();

    $this->actingAs($usuario)->get(route('admin.configuracion-pagos.index'))->assertForbidden();
    $this->actingAs($usuario)->post(route('admin.configuracion-pagos.store'), [])->assertForbidden();
    $this->actingAs($usuario)->patch(route('admin.configuracion-pagos.update', $configuracion), [])->assertForbidden();
    $this->actingAs($usuario)->get(route('admin.pagos.index'))->assertForbidden();
    $this->actingAs($usuario)->post(route('admin.pagos.pagar', $obligacion), [])->assertForbidden();
    $this->actingAs($usuario)->post(route('admin.pagos.generar'))->assertForbidden();
    $this->actingAs($usuario)->get(route('admin.pagos.comprobante', $obligacion))->assertForbidden();
})->with(['ACOPIADOR', 'PROVEEDOR', 'CALIDAD', 'ENCARGADO_PLANTA', 'VENTAS']);

it('ya no existe la pantalla de Liquidaciones para el administrador', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get('/admin/liquidaciones')->assertNotFound();
    $this->actingAs($admin)->post('/admin/liquidaciones/generar')->assertNotFound();
    $this->actingAs($admin)->get('/admin/precios-leche')->assertNotFound();
    $this->actingAs($admin)->get('/admin/reglas-pago')->assertNotFound();
});

// --------------------------------------------------------------------------
// Pagos
// --------------------------------------------------------------------------

it('la pantalla de pagos muestra KPIs, filtros, la tabla y el botón solo en filas pendientes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = pendiente_de_acopiador($admin);

    $html = $this->actingAs($admin)->get(route('admin.pagos.index'))
        ->assertOk()
        ->assertSee('Aquí se muestran los pagos pendientes y pagados generados desde Configuración de pagos.')
        ->assertSeeInOrder(['Pendientes de pago', 'Pagados este mes', 'Total pendiente', 'Total pagado'])
        ->assertSeeInOrder(['Beneficiario', 'Tipo', 'Base de cálculo', 'Periodo', 'Monto', 'Estado', 'Acción'])
        ->assertSee('Buscar beneficiario')
        ->assertSee('Julio Apellido')
        ->assertSee('Acopiador')
        ->assertSee('Sueldo fijo mensual')
        ->assertSee('Septiembre 2026')
        ->assertSee('S/ 1,000.00')
        ->assertSee('Pendiente')
        ->getContent();

    expect(substr_count($html, 'data-registrar-pago="'))->toBe(1)
        ->and($html)->toContain('data-id="'.$obligacion->id.'"')
        ->and($html)->not->toContain('+ Registrar pago')
        ->and($html)->not->toContain('Nuevo pago');
});

it('no se puede crear un pago ni un beneficiario manualmente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->post('/admin/pagos', ['monto' => 500])->assertStatus(405);
    $this->actingAs($admin)->get('/admin/pagos/create')->assertNotFound();
});

it('filtra los pagos por beneficiario, tipo, periodo y estado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Julio');
    pagos_configurar('ACOPIADOR', 1000, '2026-08-01', $admin);
    // Julio abre (y se queda) en agosto: la generación solo abre el mes
    // calendario actual, nunca uno pasado ni futuro.
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-08-20'));

    pagos_personal('CALIDAD', 'Ruth');
    pagos_configurar('CALIDAD', 1100, '2026-09-01', $admin);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    $this->actingAs($admin)->get(route('admin.pagos.index', ['q' => 'Ruth']))->assertSee('Ruth Apellido')->assertDontSee('Julio Apellido');
    $this->actingAs($admin)->get(route('admin.pagos.index', ['tipo' => 'ACOPIADOR']))->assertSee('Julio Apellido')->assertDontSee('Ruth Apellido');
    $this->actingAs($admin)->get(route('admin.pagos.index', ['periodo' => '2026-08']))->assertSee('Agosto 2026')->assertDontSee('Ruth Apellido');
    $this->actingAs($admin)->get(route('admin.pagos.index', ['estado' => 'PAGADO']))->assertSee('No se encontraron pagos')->assertDontSee('Ruth Apellido');
});

it('registra el pago desde una fila pendiente y guarda el comprobante', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = pendiente_de_acopiador($admin);

    $this->actingAs($admin)->post(route('admin.pagos.pagar', $obligacion), [
        'metodo_pago' => 'TRANSFERENCIA',
        'numero_operacion' => 'OP-778899',
        'fecha_pago' => now()->toDateString(),
        'monto' => '1000.00',
        'observacion' => 'Pago de septiembre',
        'comprobante' => UploadedFile::fake()->create('voucher.pdf', 80, 'application/pdf'),
    ])->assertRedirect()->assertSessionHas('success');

    $pago = PagoModel::firstOrFail();

    expect($obligacion->fresh()->estado)->toBe('PAGADO')
        ->and($pago->registrado_por_user_id)->toBe($admin->id)
        ->and($pago->metodo_pago)->toBe('TRANSFERENCIA')
        ->and($pago->numero_operacion)->toBe('OP-778899')
        ->and($pago->comprobante_path)->not->toBeNull();

    Storage::disk('local')->assertExists($pago->comprobante_path);
});

it('la fila pagada muestra Ver detalle y ya no permite registrar otro pago', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = pendiente_de_acopiador($admin);
    $this->actingAs($admin)->post(route('admin.pagos.pagar', $obligacion), [
        'metodo_pago' => 'EFECTIVO', 'fecha_pago' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $html = $this->actingAs($admin)->get(route('admin.pagos.index'))->assertOk()->assertSee('Ver detalle')->assertSee('Pagado')->getContent();

    expect($html)->not->toContain('data-id="'.$obligacion->id.'"');

    $this->actingAs($admin)->from(route('admin.pagos.index'))->post(route('admin.pagos.pagar', $obligacion), [
        'metodo_pago' => 'EFECTIVO', 'fecha_pago' => now()->toDateString(),
    ])->assertSessionHasErrors('pago');

    expect(PagoModel::count())->toBe(1);
});

it('valida en el backend el método, la operación, la fecha, el comprobante y el monto', function (array $datos, string $campo) {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = pendiente_de_acopiador($admin);

    $base = ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => now()->toDateString()];

    $this->actingAs($admin)->from(route('admin.pagos.index'))
        ->post(route('admin.pagos.pagar', $obligacion), array_merge($base, $datos))
        ->assertSessionHasErrors($campo);

    expect($obligacion->fresh()->estado)->toBe('PENDIENTE')->and(PagoModel::count())->toBe(0);
})->with([
    'sin método' => [['metodo_pago' => ''], 'metodo_pago'],
    'método inválido' => [['metodo_pago' => 'CHEQUE'], 'metodo_pago'],
    'yape sin operación' => [['metodo_pago' => 'YAPE'], 'numero_operacion'],
    'transferencia sin operación' => [['metodo_pago' => 'TRANSFERENCIA', 'numero_operacion' => ''], 'numero_operacion'],
    'plin sin operación' => [['metodo_pago' => 'PLIN'], 'numero_operacion'],
    'depósito sin operación' => [['metodo_pago' => 'DEPOSITO'], 'numero_operacion'],
    'sin fecha' => [['fecha_pago' => ''], 'fecha_pago'],
    'fecha futura' => [['fecha_pago' => '2099-01-01'], 'fecha_pago'],
    'monto distinto' => [['monto' => '999.00'], 'pago'],
    'comprobante de tipo inválido' => [['comprobante' => 'archivo-falso'], 'comprobante'],
]);

it('rechaza un comprobante ejecutable o demasiado pesado', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = pendiente_de_acopiador($admin);
    $base = ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => now()->toDateString()];

    $this->actingAs($admin)->post(route('admin.pagos.pagar', $obligacion), $base + ['comprobante' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('comprobante');
    $this->actingAs($admin)->post(route('admin.pagos.pagar', $obligacion), $base + ['comprobante' => UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('comprobante');
    $this->actingAs($admin)->post(route('admin.pagos.pagar', $obligacion), $base + ['comprobante' => UploadedFile::fake()->image('foto.webp')])->assertSessionHasNoErrors();

    expect(PagoModel::count())->toBe(1);
});

it('sirve el comprobante solo al administrador y responde 404 si no hay archivo', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = pendiente_de_acopiador($admin);

    $this->actingAs($admin)->get(route('admin.pagos.comprobante', $obligacion))->assertNotFound();

    $this->actingAs($admin)->post(route('admin.pagos.pagar', $obligacion), [
        'metodo_pago' => 'EFECTIVO', 'fecha_pago' => now()->toDateString(),
        'comprobante' => UploadedFile::fake()->create('voucher.pdf', 20, 'application/pdf'),
    ]);

    $this->actingAs($admin)->get(route('admin.pagos.comprobante', $obligacion))->assertOk();
});

it('el botón Actualizar pendientes genera los pagos del periodo sin duplicar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('VENTAS', 'Vero');
    pagos_configurar('VENTAS', 1000, now()->startOfMonth()->toDateString(), $admin);

    $this->actingAs($admin)->post(route('admin.pagos.generar'))->assertRedirect(route('admin.pagos.index'))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.pagos.generar'))->assertRedirect(route('admin.pagos.index'));

    expect(ObligacionPagoModel::where('beneficiario_nombre', 'Vero Apellido')->count())->toBe(1);
});

// --------------------------------------------------------------------------
// Comando y scheduler
// --------------------------------------------------------------------------

it('el comando ecolact:generar-pagos es idempotente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ENCARGADO_PLANTA', 'Elena');
    pagos_configurar('ENCARGADO_PLANTA', 1300, '2026-09-01', $admin);

    $this->artisan('ecolact:generar-pagos', ['--fecha' => '2026-09-20'])
        ->expectsOutputToContain('Pagos pendientes creados: 1')
        ->assertSuccessful();

    $this->artisan('ecolact:generar-pagos', ['--fecha' => '2026-09-20'])
        ->expectsOutputToContain('Pagos pendientes creados: 0')
        ->assertSuccessful();

    expect(ObligacionPagoModel::count())->toBe(1);
});

it('el comando rechaza una fecha inválida', function () {
    $this->artisan('ecolact:generar-pagos', ['--fecha' => 'ayer-por-la-tarde'])->assertFailed();
});

it('el scheduler ejecuta la generación de pagos todos los días', function () {
    $eventos = collect(app(Schedule::class)->events())
        ->filter(fn ($evento) => str_contains($evento->command, 'ecolact:generar-pagos'));

    expect($eventos)->toHaveCount(1)
        ->and($eventos->first()->expression)->toBe('0 1 * * *')
        ->and($eventos->first()->withoutOverlapping)->toBeTrue();
});
