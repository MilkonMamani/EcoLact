<?php

use App\Application\Pago\ActualizarConfiguracionPago;
use App\Application\Pago\GenerarPagosPendientes;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Application\Pago\ResultadoGeneracionPagos;
use App\Application\Pago\SincronizarPagosProveedor;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoDetalleModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function generar_pagos(string $hoy): ResultadoGeneracionPagos
{
    return app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable($hoy));
}

// --------------------------------------------------------------------------
// Personal: ventana de 30 días por rol (EN_CURSO hasta completar los días)
// --------------------------------------------------------------------------

it('abre la obligación EN_CURSO del mes calendario de una analista de calidad activa, con su valor por día calculado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ruth = pagos_personal('CALIDAD', 'Ruth');
    pagos_configurar('CALIDAD', 1100, '2026-09-01', $admin);

    $resultado = generar_pagos('2026-09-20');

    $obligacion = ObligacionPagoModel::firstOrFail();

    // Ruth ingresó mucho antes de septiembre y sigue activa: al no tener
    // faltas todavía, el acumulado provisional muestra el sueldo completo
    // (sección 38: EN_CURSO puede actualizar el acumulado provisional).
    expect($resultado->creadas)->toBe(1)
        ->and($obligacion->user_id)->toBe($ruth->id)
        ->and($obligacion->beneficiario_nombre)->toBe('Ruth Apellido')
        ->and($obligacion->tipo_beneficiario)->toBe('PERSONAL')
        ->and($obligacion->periodo_inicio->format('Y-m-d'))->toBe('2026-09-01')
        ->and($obligacion->periodo_fin->format('Y-m-d'))->toBe('2026-09-30')
        ->and($obligacion->fecha_pago_programada->format('Y-m-d'))->toBe('2026-10-01')
        ->and($obligacion->periodicidad)->toBe('MENSUAL')
        ->and((float) $obligacion->monto_calculado)->toBe(1100.0)
        ->and((float) $obligacion->valor_dia)->toBe(round(1100 / 30, 2))
        ->and((int) $obligacion->dias_trabajados)->toBe(0)
        ->and($obligacion->estado)->toBe('EN_CURSO')
        ->and($obligacion->proveedor_id)->toBeNull();
});

it('abre la ventana para cada rol con su valor por día, nunca por litros acopiados', function (string $rol) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal($rol, 'Trabajador');
    pagos_configurar($rol, 1500, '2026-09-01', $admin);

    generar_pagos('2026-09-20');

    $obligacion = ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail();

    expect((float) $obligacion->valor_dia)->toBe(50.0)
        ->and($obligacion->concepto)->toBe('Sueldo fijo mensual')
        ->and($obligacion->base_calculo['tipo_calculo'])->toBe('SUELDO_FIJO')
        ->and($obligacion->base_calculo['sueldo_base'])->toEqual(1500)
        ->and($obligacion->base_calculo)->not->toHaveKey('litros_pagables');
})->with(['ACOPIADOR', 'CALIDAD', 'ENCARGADO_PLANTA', 'VENTAS', 'ADMINISTRADOR']);

it('el sueldo es del rol: todos sus trabajadores generan pago con el mismo sueldo, sin configurarlos uno por uno', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    $pedro = pagos_personal('ACOPIADOR', 'Pedro');
    pagos_personal('VENTAS', 'Sin sueldo de rol');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);

    generar_pagos('2026-09-20');

    expect(ObligacionPagoModel::orderBy('user_id')->pluck('user_id')->all())->toBe([$juan->id, $pedro->id])
        ->and(ObligacionPagoModel::pluck('base_calculo')->map(fn (array $base): float => (float) $base['sueldo_base'])->unique()->all())->toBe([1500.0]);
});

it('dos acopiadores del mismo rol abren dos ventanas EN_CURSO con el mismo valor por día', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    $pedro = pagos_personal('ACOPIADOR', 'Pedro');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);

    generar_pagos('2026-09-20');

    $obligaciones = ObligacionPagoModel::orderBy('id')->get();

    expect($obligaciones)->toHaveCount(2)
        ->and($obligaciones->pluck('user_id')->all())->toEqualCanonicalizing([$juan->id, $pedro->id])
        ->and($obligaciones->map(fn ($o) => (float) $o->valor_dia)->unique()->all())->toBe([round(1000 / 30, 2)])
        ->and($obligaciones->pluck('estado')->unique()->all())->toBe(['EN_CURSO']);
});

it('no duplica un pago aunque la generación corra varias veces', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('CALIDAD', 'Ana');
    pagos_configurar('CALIDAD', 1800, '2026-09-01', $admin);

    $primera = generar_pagos('2026-09-20');
    $segunda = generar_pagos('2026-09-20');
    $tercera = generar_pagos('2026-09-25');

    expect($primera->creadas)->toBe(1)
        ->and($segunda->creadas)->toBe(0)
        ->and($segunda->existentes)->toBe(1)
        ->and($tercera->creadas)->toBe(0)
        ->and(ObligacionPagoModel::count())->toBe(1);
});

it('la restricción única impide dos pagos del mismo beneficiario y periodo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal('VENTAS');
    pagos_configurar('VENTAS', 1000, '2026-09-01', $admin);
    generar_pagos('2026-09-20');
    $existente = ObligacionPagoModel::firstOrFail();

    expect(fn () => ObligacionPagoModel::create($existente->only([
        'tipo_beneficiario', 'user_id', 'beneficiario_nombre', 'periodo_inicio', 'periodo_fin', 'periodicidad',
        'concepto', 'monto_calculado', 'estado', 'clave_unicidad',
    ])))->toThrow(UniqueConstraintViolationException::class);

    expect($persona->id)->toBe($existente->user_id);
});

it('el personal inactivo no genera pagos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Inactivo', estado: 'INACTIVO');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);

    generar_pagos('2026-09-20');

    expect(ObligacionPagoModel::count())->toBe(0);
});

it('conserva los pagos históricos de quien luego quedó inactivo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal('ACOPIADOR');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);
    generar_pagos('2026-09-20');

    $persona->update(['estado' => 'INACTIVO']);
    $persona->acopiador->update(['estado' => 'INACTIVO']);
    generar_pagos('2026-10-05');

    expect(ObligacionPagoModel::where('user_id', $persona->id)->pluck('periodo_inicio')->map->format('Y-m')->all())->toBe(['2026-09']);
});

it('un trabajador que ingresó a mitad de mes se incorpora al mes calendario actual, no a uno anterior a su alta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Antiguo', '2026-01-10');
    pagos_personal('ACOPIADOR', 'Nuevo', '2026-09-05');
    pagos_configurar('ACOPIADOR', 1000, '2026-07-01', $admin);

    generar_pagos('2026-09-20');

    $nuevo = ObligacionPagoModel::where('beneficiario_nombre', 'Nuevo Apellido')->get();
    $antiguo = ObligacionPagoModel::where('beneficiario_nombre', 'Antiguo Apellido')->get();

    // Mes calendario: ambos comparten el mismo periodo (septiembre), sin
    // importar que "Nuevo" haya ingresado a mitad de mes; nunca se abre un
    // periodo anterior a su fecha de alta.
    expect($nuevo)->toHaveCount(1)
        ->and($nuevo->first()->periodo_inicio->format('Y-m-d'))->toBe('2026-09-01')
        ->and($antiguo)->toHaveCount(1)
        ->and($antiguo->first()->periodo_inicio->format('Y-m-d'))->toBe('2026-09-01');
});

it('un trabajador cuya alta es posterior al mes calendario actual no abre obligación hasta que ese mes llegue', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Futuro', '2026-10-05');
    pagos_configurar('ACOPIADOR', 1000, '2026-07-01', $admin);

    generar_pagos('2026-09-20');
    expect(ObligacionPagoModel::count())->toBe(0);

    generar_pagos('2026-10-10');
    $obligacion = ObligacionPagoModel::firstOrFail();
    expect($obligacion->periodo_inicio->format('Y-m-d'))->toBe('2026-10-01');
});

it('una configuración futura no genera pagos hasta que comienza su vigencia', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('CALIDAD');
    pagos_configurar('CALIDAD', 1100, '2026-10-01', $admin);

    generar_pagos('2026-09-20');
    expect(ObligacionPagoModel::count())->toBe(0);

    generar_pagos('2026-10-01');
    expect(ObligacionPagoModel::pluck('periodo_inicio')->map->format('Y-m')->all())->toBe(['2026-10']);
});

it('un cambio de sueldo solo aplica al mes que abre después de pagar el anterior, sin recalcular el histórico', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal('ACOPIADOR');
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    generar_pagos('2026-09-20');

    $sept = ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail();
    // Fuerza septiembre a PENDIENTE sin pasar por el cierre real (que ya
    // abriría octubre con la config vieja): este test solo quiere aislar
    // que un cambio de sueldo posterior no reescribe un histórico pagado.
    $sept->update(['dias_trabajados' => 30, 'valor_dia' => 30.0, 'acumulado' => 900.0, 'monto_calculado' => 900.0, 'estado' => 'PENDIENTE']);
    pagar_obligacion($sept->fresh(), $admin);

    $original = ConfiguracionPagoModel::firstOrFail();
    app(ActualizarConfiguracionPago::class)->execute($original->id, 1100.0, false, new DateTimeImmutable('2026-10-01'), null, $admin->id);
    generar_pagos('2026-10-15');

    $obligaciones = ObligacionPagoModel::where('user_id', $persona->id)->orderBy('periodo_inicio')->get();
    $oct = $obligaciones->firstWhere('id', '!=', $sept->id);

    expect((float) $sept->fresh()->monto_calculado)->toBe(900.0)
        ->and($sept->fresh()->estado)->toBe('PAGADO')
        ->and($oct)->not->toBeNull()
        ->and((float) $oct->valor_dia)->toBe(round(1100 / 30, 2))
        ->and($oct->estado)->toBe('EN_CURSO');
});

it('la ventana abierta conserva su valor por día aunque el sueldo cambie después', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ENCARGADO_PLANTA');
    pagos_configurar('ENCARGADO_PLANTA', 1300, '2026-09-01', $admin);
    generar_pagos('2026-09-20');

    ConfiguracionPagoModel::firstOrFail()->update(['monto_base' => 9999]);
    generar_pagos('2026-09-21');

    expect((float) ObligacionPagoModel::firstOrFail()->valor_dia)->toBe(round(1300 / 30, 2));
});

// --------------------------------------------------------------------------
// Proveedores: cada entrega válida genera deuda (litros x precio base +/- ajuste)
// --------------------------------------------------------------------------

function proveedor_con_leche(User $admin, array $entregas, bool $aplicaCalidad = true, float $precio = 2.10): array
{
    pagos_precios_y_reglas($admin, $precio);
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, aplicaCalidad: $aplicaCalidad);
    $proveedor = fixture_proveedor(fixture_zona());
    $proveedor->update(['nombres' => 'Ana', 'apellidos' => 'Quispe']);

    foreach ($entregas as [$litros, $fecha, $resultado]) {
        pagos_entrega($proveedor, $litros, $fecha, $resultado);
    }

    return [$proveedor->fresh(), $admin];
}

function pagar_obligacion(ObligacionPagoModel $obligacion, User $admin): void
{
    app(RegistrarPagoObligacion::class)->execute($obligacion->id, MetodoPago::EFECTIVO, null, new DateTimeImmutable('2026-09-20'), null, null, $admin->id, null, new DateTimeImmutable('2026-09-20'));
}

it('genera el pago del proveedor: litros válidos por el precio de leche vigente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    // Semana de EcoLact: jueves 2026-09-10 a miércoles 2026-09-16.
    [$proveedor] = proveedor_con_leche($admin, [[60.0, '2026-09-10', 'ACEPTADA'], [40.0, '2026-09-12', 'ACEPTADA']]);

    $resultado = generar_pagos('2026-09-20');

    $obligacion = ObligacionPagoModel::where('tipo_beneficiario', 'PROVEEDOR')->firstOrFail();

    expect($resultado->creadas)->toBe(1)
        ->and($obligacion->proveedor_id)->toBe($proveedor->id)
        ->and($obligacion->beneficiario_nombre)->toBe('Ana Quispe')
        ->and($obligacion->periodicidad)->toBe('SEMANAL')
        ->and($obligacion->periodo_inicio->format('Y-m-d'))->toBe('2026-09-10')
        ->and($obligacion->periodo_fin->format('Y-m-d'))->toBe('2026-09-16')
        ->and((float) $obligacion->monto_calculado)->toBe(210.0)
        ->and($obligacion->estado)->toBe('PENDIENTE')
        ->and($obligacion->base_calculo['litros_pagables'])->toEqual(100)
        ->and($obligacion->base_calculo['precio_litro_aplicado'])->toEqual(2.1)
        ->and($obligacion->detalles)->toHaveCount(2)
        ->and($obligacion->liquidacion_id)->toBeNull();
});

it('el cálculo del proveedor usa el precio de leche vigente, no un valor de la configuración', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[50.0, '2026-09-10', 'ACEPTADA']], precio: 3.00);

    generar_pagos('2026-09-20');

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(150.0)
        ->and(ConfiguracionPagoModel::firstOrFail()->monto_base)->toBeNull();
});

it('caso A: todas las entregas ACEPTADAS se pagan al precio normal, sin ajuste', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[50.0, '2026-09-10', 'ACEPTADA'], [50.0, '2026-09-11', 'ACEPTADA']], aplicaCalidad: true);

    generar_pagos('2026-09-20');

    $obligacion = ObligacionPagoModel::firstOrFail();

    expect((float) $obligacion->monto_calculado)->toBe(210.0)
        ->and($obligacion->base_calculo['resultado_semanal'])->toBe('ACEPTADA')
        ->and($obligacion->detalles->map(fn ($d): float => (float) $d->ajuste_calidad)->unique()->values()->all())->toBe([0.0]);
});

it('caso B: una sola entrega OBSERVADA aplica el descuento a TODOS los litros de la semana', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[50.0, '2026-09-10', 'ACEPTADA'], [50.0, '2026-09-11', 'OBSERVADA']], aplicaCalidad: true);

    generar_pagos('2026-09-20');

    $obligacion = ObligacionPagoModel::firstOrFail();
    $detalles = $obligacion->detalles->sortBy('entrega_id')->values();

    // Semana OBSERVADA: 100 x (2.10 - 0.30) = 180, también la entrega aceptada.
    expect((float) $obligacion->monto_calculado)->toBe(180.0)
        ->and($obligacion->base_calculo['resultado_semanal'])->toBe('OBSERVADA')
        ->and((float) $detalles[0]->precio_final)->toBe(1.8)
        ->and((float) $detalles[0]->ajuste_calidad)->toBe(-0.3)
        ->and($detalles[0]->resultado_calidad)->toBe('ACEPTADA')
        ->and((float) $detalles[1]->precio_final)->toBe(1.8);
});

it('caso C: varias entregas OBSERVADAS aplican el mismo descuento semanal una sola vez por litro', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[50.0, '2026-09-10', 'OBSERVADA'], [50.0, '2026-09-11', 'OBSERVADA']], aplicaCalidad: true);

    generar_pagos('2026-09-20');

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(180.0);
});

it('caso D y E: una entrega RECHAZADA deja la semana no pagable, aunque haya aceptadas u observadas', function (array $entregas) {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, $entregas, aplicaCalidad: true);

    $resultado = generar_pagos('2026-09-20');

    $semana = ObligacionPagoModel::sole();

    // La semana queda registrada como NO_PAGABLE con S/ 0.00: visible, pero nunca pagable.
    expect($resultado->creadas)->toBe(0)
        ->and($semana->estado)->toBe('NO_PAGABLE')
        ->and((float) $semana->monto_calculado)->toBe(0.0)
        ->and($semana->base_calculo['resultado_semanal'])->toBe('RECHAZADA')
        ->and((float) $semana->base_calculo['precio_base_litro'])->toBe(2.1)
        ->and((float) $semana->base_calculo['litros_entregados'])->toBe(100.0)
        ->and((float) $semana->base_calculo['litros_pagables'])->toBe(0.0)
        ->and(json_encode($resultado->omitidas, JSON_UNESCAPED_UNICODE))->toContain('RECHAZADA');
})->with([
    'aceptada + rechazada' => [[[40.0, '2026-09-10', 'ACEPTADA'], [60.0, '2026-09-11', 'RECHAZADA']]],
    'observada + rechazada' => [[[40.0, '2026-09-10', 'OBSERVADA'], [60.0, '2026-09-11', 'RECHAZADA']]],
]);

it('caso F: una entrega sin análisis toma el resultado del resto de la semana', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[50.0, '2026-09-10', null], [50.0, '2026-09-11', 'OBSERVADA']], aplicaCalidad: true);

    generar_pagos('2026-09-20');

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(180.0);
});

it('sin ajustes de calidad se paga el precio base aunque haya incidencias', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[50.0, '2026-09-10', 'ACEPTADA'], [50.0, '2026-09-11', 'OBSERVADA']], aplicaCalidad: false);

    generar_pagos('2026-09-20');

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(210.0);
});

it('una entrega sin análisis de calidad SÍ genera deuda al precio base y no crea un análisis', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[100.0, '2026-09-10', null]]);

    generar_pagos('2026-09-20');

    $obligacion = ObligacionPagoModel::firstOrFail();

    expect((float) $obligacion->monto_calculado)->toBe(210.0)
        ->and($obligacion->base_calculo['tuvo_incidencia'])->toBeFalse()
        ->and($obligacion->detalles->first()->resultado_calidad)->toBeNull()
        ->and(AnalisisCalidadModel::count())->toBe(0);
});

it('la leche completamente rechazada no genera un pago: la semana queda NO_PAGABLE', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[30.0, '2026-09-10', 'RECHAZADA'], [20.0, '2026-09-11', 'RECHAZADA']]);

    $resultado = generar_pagos('2026-09-20');

    expect($resultado->creadas)->toBe(0)
        ->and(ObligacionPagoModel::where('estado', 'PENDIENTE')->count())->toBe(0)
        ->and(ObligacionPagoModel::sole()->estado)->toBe('NO_PAGABLE');
});

it('las compras de una semana RECHAZADA se descuentan en la siguiente semana pagable', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedor_con_leche($admin, [[40.0, '2026-09-10', 'RECHAZADA'], [50.0, '2026-09-17', 'ACEPTADA']]);
    $periodo = PeriodoLiquidacionModel::create(['fecha_inicio' => '2026-09-10', 'fecha_fin' => '2026-09-16', 'estado' => 'ABIERTO']);
    $producto = ProductoModel::create(['nombre' => 'Queso', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    VentaProductoModel::create([
        'producto_id' => $producto->id, 'proveedor_id' => $proveedor->id, 'tipo_cliente' => 'PROVEEDOR',
        'cantidad' => 1, 'precio_unitario' => 20, 'importe_total' => 20, 'forma_pago' => 'DESCUENTO_PLANILLA',
        'periodo_id' => $periodo->id, 'estado' => 'REGISTRADA', 'fecha' => '2026-09-12', 'registrado_por_user_id' => $admin->id,
    ]);

    generar_pagos('2026-09-27');

    $rechazada = ObligacionPagoModel::where('estado', 'NO_PAGABLE')->sole();
    $siguiente = ObligacionPagoModel::where('estado', 'PENDIENTE')->sole();

    // Semana siguiente: 50 L × 2.10 = 105 − 20 de la compra hecha en la semana rechazada.
    expect($rechazada->base_calculo['descuentos'])->toBe([])
        ->and((float) $siguiente->monto_calculado)->toBe(85.0);
});

it('una semana NO_PAGABLE no se puede pagar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[30.0, '2026-09-10', 'RECHAZADA']]);
    generar_pagos('2026-09-20');
    $semana = ObligacionPagoModel::sole();

    expect(fn () => pagar_obligacion($semana, $admin))->toThrow(DomainException::class);

    expect($semana->fresh()->estado)->toBe('NO_PAGABLE');
});

it('un análisis en borrador todavía no ajusta el precio: se paga el precio base', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[60.0, '2026-09-10', 'ACEPTADA'], [40.0, '2026-09-11', 'BORRADOR']]);

    generar_pagos('2026-09-20');

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(210.0);
});

it('la semana en curso ya genera su pago pendiente, sin esperar a que cierre', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[100.0, '2026-09-18', 'ACEPTADA']]);

    generar_pagos('2026-09-20');

    expect(ObligacionPagoModel::count())->toBe(1);
});

it('las entregas anteriores al inicio de la configuración no se pierden', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    pagos_configurar('PROVEEDOR', null, '2026-09-21', $admin);
    $proveedor = fixture_proveedor(fixture_zona());
    pagos_entrega($proveedor, 20.0, '2026-09-15');

    generar_pagos('2026-09-21');

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(42.0);
});

it('sin una configuración de pago del proveedor no se genera ningún pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    $proveedor = fixture_proveedor(fixture_zona());
    pagos_entrega($proveedor, 100.0, '2026-09-10', 'ACEPTADA');

    generar_pagos('2026-09-20');

    expect(ObligacionPagoModel::count())->toBe(0);
});

it('una entrega sin precio de leche vigente queda sin asignar en vez de fallar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 2.10);
    PrecioLecheModel::query()->update(['fecha_inicio' => '2026-12-01']);
    $proveedor = fixture_proveedor(fixture_zona());
    pagos_entrega($proveedor, 100.0, '2026-09-10');

    $resultado = generar_pagos('2026-09-20');

    expect($resultado->creadas)->toBe(0)
        ->and($resultado->omitidas)->toHaveCount(1)
        ->and(ObligacionPagoModel::count())->toBe(0);
});

it('el pago del proveedor no se duplica al generar varias veces', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[100.0, '2026-09-10', 'ACEPTADA']]);

    generar_pagos('2026-09-20');
    $id = ObligacionPagoModel::firstOrFail()->id;
    $segunda = generar_pagos('2026-09-21');
    generar_pagos('2026-09-22');

    expect(ObligacionPagoModel::count())->toBe(1)
        ->and(ObligacionPagoModel::firstOrFail()->id)->toBe($id)
        ->and(ObligacionPagoDetalleModel::count())->toBe(1)
        ->and($segunda->creadas)->toBe(0)
        ->and($segunda->existentes)->toBe(1);
});

it('las entregas de semanas distintas forman pagos distintos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, [[20.0, '2026-09-15', null], [20.0, '2026-09-17', null]]);

    generar_pagos('2026-09-20');

    $pagos = ObligacionPagoModel::orderBy('periodo_inicio')->get();

    expect($pagos)->toHaveCount(2)
        ->and($pagos->pluck('periodo_inicio')->map->format('Y-m-d')->all())->toBe(['2026-09-10', '2026-09-17'])
        ->and($pagos->map(fn ($p) => (float) $p->monto_calculado)->all())->toBe([42.0, 42.0]);
});

it('una entrega nueva de la misma semana se suma al pago pendiente abierto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedor_con_leche($admin, [[20.0, '2026-09-10', null]], precio: 1.70);
    generar_pagos('2026-09-20');
    $id = ObligacionPagoModel::firstOrFail()->id;

    pagos_entrega($proveedor, 10.0, '2026-09-12');
    app(SincronizarPagosProveedor::class)->execute($proveedor->id);

    $obligacion = ObligacionPagoModel::firstOrFail();

    expect(ObligacionPagoModel::count())->toBe(1)
        ->and($obligacion->id)->toBe($id)
        ->and((float) $obligacion->monto_calculado)->toBe(51.0)
        ->and($obligacion->detalles)->toHaveCount(2);
});

it('un análisis confirmado recalcula el pago mientras siga pendiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedor_con_leche($admin, [[100.0, '2026-09-10', null]], precio: 1.70);
    generar_pagos('2026-09-20');
    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(170.0);

    $entrega = $proveedor->entregas()->firstOrFail();
    fixture_analisis_confirmado($entrega, 'OBSERVADA', $admin->id);
    app(SincronizarPagosProveedor::class)->execute($proveedor->id);

    // 1.70 - 0.30 = 1.40 x 100 L.
    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(140.0)
        ->and(ObligacionPagoModel::count())->toBe(1);
});

it('un pago PAGADO es inmutable: las entregas nuevas forman otro pendiente y no lo alteran', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedor_con_leche($admin, [[30.0, '2026-09-14', null]], precio: 1.70);
    generar_pagos('2026-09-20');
    $pagado = ObligacionPagoModel::firstOrFail();
    pagar_obligacion($pagado, $admin);

    pagos_entrega($proveedor, 20.0, '2026-09-15');
    generar_pagos('2026-09-20');
    generar_pagos('2026-09-20');

    $pagos = ObligacionPagoModel::orderBy('id')->get();

    expect($pagos)->toHaveCount(2)
        ->and($pagos[0]->id)->toBe($pagado->id)
        ->and($pagos[0]->estado)->toBe('PAGADO')
        ->and((float) $pagos[0]->monto_calculado)->toBe(51.0)
        ->and($pagos[0]->detalles)->toHaveCount(1)
        ->and($pagos[1]->estado)->toBe('PENDIENTE')
        ->and((float) $pagos[1]->monto_calculado)->toBe(34.0)
        ->and($pagos[1]->periodo_inicio->format('Y-m-d'))->toBe('2026-09-17');
});

it('un análisis posterior no modifica un pago ya realizado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedor_con_leche($admin, [[100.0, '2026-09-10', null]], precio: 1.70);
    generar_pagos('2026-09-20');
    pagar_obligacion(ObligacionPagoModel::firstOrFail(), $admin);

    fixture_analisis_confirmado($proveedor->entregas()->firstOrFail(), 'OBSERVADA', $admin->id);
    $resultado = app(SincronizarPagosProveedor::class)->execute($proveedor->id);

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(170.0)
        ->and(ObligacionPagoModel::count())->toBe(1)
        ->and($resultado->creadas)->toBe(0);
});

it('un proveedor sin entregas no genera pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    proveedor_con_leche($admin, []);

    $resultado = generar_pagos('2026-09-20');

    expect($resultado->creadas)->toBe(0)->and(ObligacionPagoModel::count())->toBe(0);
});

function compra_a_cuenta(User $admin, int $proveedorId, float $importe): void
{
    $periodo = PeriodoLiquidacionModel::create(['fecha_inicio' => '2026-09-10', 'fecha_fin' => '2026-09-16', 'estado' => 'ABIERTO']);
    $producto = ProductoModel::create(['nombre' => 'Queso', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);

    VentaProductoModel::create([
        'producto_id' => $producto->id, 'proveedor_id' => $proveedorId, 'tipo_cliente' => 'PROVEEDOR',
        'cantidad' => 1, 'precio_unitario' => $importe, 'importe_total' => $importe, 'forma_pago' => 'DESCUENTO_PLANILLA',
        'periodo_id' => $periodo->id, 'estado' => 'REGISTRADA', 'fecha' => '2026-09-11', 'registrado_por_user_id' => $admin->id,
    ]);
}

it('descuenta del pago del proveedor las compras de producto hechas a cuenta de su pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedor_con_leche($admin, [[100.0, '2026-09-10', 'ACEPTADA']]);
    compra_a_cuenta($admin, $proveedor->id, 10.0);

    generar_pagos('2026-09-20');

    $obligacion = ObligacionPagoModel::firstOrFail();

    expect((float) $obligacion->monto_calculado)->toBe(200.0)
        ->and($obligacion->base_calculo['ajustes'])->toEqual(-10)
        ->and($obligacion->base_calculo['descuentos'][0]['importe'])->toEqual(10);
});

it('un descuento ya aplicado a un pago realizado no se descuenta otra vez', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedor_con_leche($admin, [[100.0, '2026-09-10', 'ACEPTADA']]);
    compra_a_cuenta($admin, $proveedor->id, 10.0);
    generar_pagos('2026-09-20');
    pagar_obligacion(ObligacionPagoModel::firstOrFail(), $admin);

    pagos_entrega($proveedor, 10.0, '2026-09-12');
    generar_pagos('2026-09-20');

    expect((float) ObligacionPagoModel::where('estado', 'PENDIENTE')->firstOrFail()->monto_calculado)->toBe(21.0);
});

it('cuando los descuentos igualan o superan la leche entregada no se genera el pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedor_con_leche($admin, [[10.0, '2026-09-10', 'ACEPTADA']]);
    compra_a_cuenta($admin, $proveedor->id, 50.0);

    $resultado = generar_pagos('2026-09-20');

    expect($resultado->creadas)->toBe(0)
        ->and($resultado->omitidas[0]['motivo'])->toContain('descuentos')
        ->and(ObligacionPagoModel::count())->toBe(0)
        ->and(LiquidacionModel::count())->toBe(0);
});
