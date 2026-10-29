<?php

use App\Application\Dashboard\DashboardPeriodo;
use App\Application\Dashboard\ObtenerResumenFinanciero;
use App\Application\Dashboard\ResumenFinanciero;
use App\Application\Pago\SincronizarPagosProveedor;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';
require_once __DIR__.'/../../../Support/DashboardFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * "Hoy" es el miércoles 28/10/2026 a mediodía en Lima: cierre y día de pago
 * de la semana de proveedores 22/10-28/10. Semana calendario 26/10-01/11.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00:00', 'America/Lima'));
    $this->admin = pagos_personal('ADMINISTRADOR', 'Admin', '2026-01-15');
});

function finanzas(string $periodo, ?string $desde = null, ?string $hasta = null): ResumenFinanciero
{
    return app(ObtenerResumenFinanciero::class)->execute(DashboardPeriodo::resolver($periodo, $desde, $hasta));
}

function finanzas_jornada(TrabajadorModel $trabajador, string $fecha, string $estado = 'PRESENTE'): void
{
    JornadaPersonalModel::create([
        'trabajador_id' => $trabajador->id,
        'rol_id' => User::findOrFail($trabajador->user_id)->role_id,
        'fecha' => $fecha,
        'estado' => $estado,
        'origen' => 'MANUAL',
    ]);
}

/** @return array<string, float> rol => importe */
function finanzas_por_rol(ResumenFinanciero $finanzas): array
{
    $roles = array_column($finanzas->trabajadores['porRol'], 'importe', 'rol');
    ksort($roles);

    return $roles;
}

function finanzas_entrega(ProveedorModel $proveedor, float $litros, string $fechaLocal, ?string $resultado = null): EntregaModel
{
    return pagos_entrega($proveedor, $litros, dashboard_utc($fechaLocal), $resultado);
}

function finanzas_muestra_pendiente(EntregaModel $entrega, User $analista): void
{
    DB::table('muestreos_calidad')->insert([
        'entrega_id' => $entrega->id,
        'seleccionado_por_user_id' => $analista->id,
        'metodo_seleccion' => 'MANUAL',
        'tipo_control' => 'RUTINARIO',
        'fecha_seleccion' => now(),
        'estado' => 'SELECCIONADA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function finanzas_pago(ObligacionPagoModel $obligacion, string $monto, string $fecha, User $admin): void
{
    PagoModel::create([
        'obligacion_pago_id' => $obligacion->id,
        'fecha_pago' => $fecha,
        'pagado_at' => $fecha.' 10:00:00',
        'monto' => $monto,
        'metodo_pago' => 'EFECTIVO',
        'registrado_por_user_id' => $admin->id,
    ]);
}

function finanzas_sueldos(User $admin, string $desde = '2026-01-01'): void
{
    foreach (['ADMINISTRADOR' => 2000, 'ACOPIADOR' => 1500, 'CALIDAD' => 1800, 'ENCARGADO_PLANTA' => 1800, 'VENTAS' => 1800] as $rol => $sueldo) {
        pagos_configurar($rol, $sueldo, $desde, $admin);
    }
}

it('devenga hoy el sueldo diario de todos los roles remunerables según asistencia y excluye al proveedor', function () {
    finanzas_sueldos($this->admin);
    $acopiador = pagos_trabajador(pagos_personal('ACOPIADOR', 'Presente'));
    $ausente = pagos_trabajador(pagos_personal('ACOPIADOR', 'Ausente'));
    $sinMarcar = pagos_trabajador(pagos_personal('ENCARGADO_PLANTA', 'Sin marcar'));
    $calidad = pagos_trabajador(pagos_personal('CALIDAD', 'Analista'));
    $ventas = pagos_trabajador(pagos_personal('VENTAS', 'Vendedor'));
    pagos_trabajador($this->admin);
    fixture_proveedor(fixture_zona());

    finanzas_jornada($acopiador, '2026-10-28');
    finanzas_jornada($ausente, '2026-10-28', 'FALTA');
    finanzas_jornada($calidad, '2026-10-28');
    finanzas_jornada($ventas, '2026-10-28');

    $hoy = finanzas('hoy');

    // Administrador sin control de asistencia: cada día vigente; el resto solo con PRESENTE.
    expect(finanzas_por_rol($hoy))->toBe(['ACOPIADOR' => 50.0, 'ADMINISTRADOR' => 66.67, 'CALIDAD' => 60.0, 'VENTAS' => 60.0]);
    expect($hoy->trabajadores['devengado'])->toBe(236.67);
    expect($hoy->trabajadores['dias'])->toBe(4);
    expect(array_column($hoy->trabajadores['porTrabajador'], 'nombre'))->not->toContain($sinMarcar->nombres.' '.$sinMarcar->apellidos);
    expect(round(array_sum(array_column($hoy->trabajadores['porRol'], 'importe')), 2))->toBe($hoy->trabajadores['devengado']);
    expect(round(array_sum(array_column($hoy->trabajadores['porTrabajador'], 'importe')), 2))->toBe($hoy->trabajadores['devengado']);
    // El saldo es al corte: incluye todo octubre devengado (admin vigente desde enero: 28 × 66.67).
    expect($hoy->trabajadores['pendiente'])->toBe(2036.76);
    expect(finanzas('mes')->trabajadores['pendiente'])->toBe(2036.76);
    expect($hoy->totales['devengado'])->toBe(236.67);
});

it('acumula la semana y el mes con el sueldo vigente cada día, sin días futuros', function () {
    pagos_configurar('ACOPIADOR', 1500, '2026-01-01', $this->admin);
    ConfiguracionPagoModel::create([
        'tipo_beneficiario' => 'PERSONAL', 'rol_id' => fixture_rol('ACOPIADOR')->id, 'user_id' => null,
        'tipo_calculo' => 'SUELDO_FIJO', 'monto_base' => 1800, 'periodicidad' => 'MENSUAL', 'aplica_calidad' => false,
        'fecha_inicio' => '2026-10-27', 'estado' => 'ACTIVO', 'creado_por_user_id' => $this->admin->id,
    ]);
    $acopiador = pagos_trabajador(pagos_personal('ACOPIADOR'));

    finanzas_jornada($acopiador, '2026-10-20');
    finanzas_jornada($acopiador, '2026-10-22', 'FALTA');
    finanzas_jornada($acopiador, '2026-10-26');
    finanzas_jornada($acopiador, '2026-10-27');
    finanzas_jornada($acopiador, '2026-10-28');

    expect(finanzas_por_rol(finanzas('hoy'))['ACOPIADOR'])->toBe(60.0);
    expect(finanzas_por_rol(finanzas('semana'))['ACOPIADOR'])->toBe(170.0);   // 50 + 60 + 60
    expect(finanzas_por_rol(finanzas('mes'))['ACOPIADOR'])->toBe(220.0);      // + 20/10 con el sueldo de entonces
    expect(finanzas('semana')->trabajadores['programado']['estimado'])->toBe(220.0); // sueldo de octubre, se paga el 01/11
});

it('informa el rol sin sueldo vigente en lugar de inventar un importe', function () {
    pagos_configurar('ACOPIADOR', 1500, '2026-10-28', $this->admin);
    $acopiador = pagos_trabajador(pagos_personal('ACOPIADOR'));
    finanzas_jornada($acopiador, '2026-10-27');
    finanzas_jornada($acopiador, '2026-10-28');

    $semana = finanzas('semana');

    expect(finanzas_por_rol($semana))->toBe(['ACOPIADOR' => 50.0]);
    expect($semana->trabajadores['avisos'])->toContain('Rol ACOPIADOR sin sueldo vigente: 1 día(s) trabajados sin valorizar (27/10/2026).');
    expect(implode(' ', $semana->trabajadores['avisos']))->toContain('Rol ADMINISTRADOR sin sueldo vigente');
});

it('un mes cerrado aporta su obligación histórica y su saldo descuenta pagos parciales y totales sin doble conteo', function () {
    pagos_configurar('ACOPIADOR', 1500, '2026-01-01', $this->admin);
    $usuario = pagos_personal('ACOPIADOR');
    $acopiador = pagos_trabajador($usuario);
    finanzas_jornada($acopiador, '2026-09-01');
    finanzas_jornada($acopiador, '2026-09-02');
    finanzas_jornada($acopiador, '2026-09-03');
    finanzas_jornada($acopiador, '2026-10-28');
    $septiembre = dashboard_obligacion_trabajador('2026-09', '2026-10-01', '1500.00');
    $septiembre->update(['user_id' => $usuario->id, 'rol_id' => $usuario->role_id, 'acumulado' => '1500.00', 'clave_unicidad' => 'PERSONAL:'.$usuario->id.':2026-09-01']);
    $octubre = dashboard_obligacion_trabajador('2026-10', '2026-11-01', '1500.00', 'EN_CURSO'); // proyección
    $octubre->update(['user_id' => $usuario->id, 'rol_id' => $usuario->role_id, 'acumulado' => '1500.00', 'clave_unicidad' => 'PERSONAL:'.$usuario->id.':2026-10-01']);

    // Septiembre cerrado: 3 días de 50 más el ajuste de cierre = lo reconocido (1500).
    $mesCerrado = finanzas('personalizado', '2026-09-01', '2026-09-30');
    expect(finanzas_por_rol($mesCerrado)['ACOPIADOR'])->toBe(1500.0);
    expect(finanzas_por_rol(finanzas('personalizado', '2026-09-01', '2026-09-15'))['ACOPIADOR'])->toBe(150.0);

    // Saldo hoy: septiembre (obligación) + octubre devengado a la fecha; la proyección EN_CURSO no suma.
    expect(finanzas('hoy')->trabajadores)->toMatchArray(['pendiente' => 1550.0, 'pendienteCerrado' => 1500.0, 'pendienteEnCurso' => 50.0, 'vencido' => 1500.0]);

    finanzas_pago($septiembre, '500.00', '2026-10-10', $this->admin);
    expect(finanzas('hoy')->trabajadores['pendienteCerrado'])->toBe(1000.0);
    // El corte histórico (05/10) todavía no conocía el pago del 10/10.
    expect(finanzas('personalizado', '2026-10-01', '2026-10-05')->trabajadores['pendienteCerrado'])->toBe(1500.0);

    // Pago total: `pagos` admite un solo registro por obligación (RegistrarPagoObligacion paga el monto exacto).
    PagoModel::where('obligacion_pago_id', $septiembre->id)->update(['monto' => '1500.00']);
    $septiembre->update(['estado' => 'PAGADO']);
    expect(finanzas('hoy')->trabajadores)->toMatchArray(['pendiente' => 50.0, 'pendienteCerrado' => 0.0, 'vencido' => 0.0]);
    expect(finanzas('personalizado', '2026-09-01', '2026-09-30')->trabajadores['devengado'])->toBe(1500.0);
});

it('valoriza a proveedores por fecha de entrega, programa por fecha de pago y separa lo sujeto a calidad', function () {
    pagos_precios_y_reglas($this->admin, 1.70);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $this->admin);
    $analista = fixture_usuario('CALIDAD');

    [$a, $b, $c, $d] = [fixture_proveedor(fixture_zona()), fixture_proveedor(fixture_zona()), fixture_proveedor(fixture_zona()), fixture_proveedor(fixture_zona())];
    finanzas_entrega($a, 100, '2026-10-28 08:00:00');
    finanzas_entrega($a, 50, '2026-10-22 08:00:00');
    finanzas_muestra_pendiente(finanzas_entrega($b, 200, '2026-10-28 18:30:00'), $analista);
    finanzas_entrega($c, 80, '2026-10-27 17:00:00');
    finanzas_entrega($d, 40, '2026-10-20 08:00:00', 'RECHAZADA'); // semana 15-21/10 no pagable
    app(SincronizarPagosProveedor::class)->execute();

    $hoy = finanzas('hoy')->proveedores;
    $semana = finanzas('semana')->proveedores;
    $mes = finanzas('mes')->proveedores;

    expect($hoy['litros'])->toBe(['recibidos' => 300.0, 'enAnalisis' => 200.0, 'valorizados' => 300.0, 'noPagables' => 0.0, 'sinValorizar' => 0.0]);
    expect($hoy['devengado'])->toBe(510.0);
    expect($hoy['sujetoACalidad'])->toBe(340.0);
    expect($semana['devengado'])->toBe(646.0);
    expect($mes['devengado'])->toBe(731.0);
    expect($mes['litros'])->toMatchArray(['recibidos' => 470.0, 'valorizados' => 430.0, 'noPagables' => 40.0]);

    // La semana 22-28/10 se paga hoy: su importe completo (incluye entregas de días anteriores).
    expect($hoy['programado'])->toBe(['importe' => 731.0, 'pagado' => 0.0, 'porPagar' => 731.0, 'obligaciones' => 3]);
    expect($semana['programado']['importe'])->toBe(731.0);
    expect($hoy['pendiente'])->toBe(731.0);
    expect($hoy['vencido'])->toBe(0.0);
    expect($hoy['semana'])->toBe('2026-10-22');

    $pagada = ObligacionPagoModel::where('tipo_beneficiario', 'PROVEEDOR')->where('monto_calculado', 340)->firstOrFail();
    finanzas_pago($pagada, '340.00', '2026-10-28', $this->admin);
    $pagada->update(['estado' => 'PAGADO']);

    $despues = finanzas('hoy');
    expect($despues->proveedores['programado'])->toMatchArray(['importe' => 731.0, 'pagado' => 340.0, 'porPagar' => 391.0]);
    expect($despues->proveedores['pendiente'])->toBe(391.0);
    expect($despues->proveedores['sujetoACalidad'])->toBe(0.0);
    expect($despues->proveedores['devengado'])->toBe(510.0);
});

it('cuenta las entregas por día de Lima con inicio incluido y fin excluido', function () {
    $proveedor = fixture_proveedor(fixture_zona());
    finanzas_entrega($proveedor, 10, '2026-10-27 23:59:59');
    finanzas_entrega($proveedor, 20, '2026-10-28 00:00:00');
    finanzas_entrega($proveedor, 40, '2026-10-28 23:59:59'); // 29/10 04:59:59 UTC

    expect(finanzas('hoy')->proveedores['litros'])->toMatchArray(['recibidos' => 60.0, 'sinValorizar' => 60.0]);
    expect(finanzas('semana')->proveedores['litros']['recibidos'])->toBe(70.0);
    expect(finanzas('personalizado', '2026-10-27', '2026-10-27')->proveedores['litros']['recibidos'])->toBe(10.0);
    // Sin configuración ni precio no hay valorización: nada se inventa.
    expect(finanzas('hoy')->proveedores['devengado'])->toBe(0.0);
});

it('el dashboard muestra el saldo reconocido por pagar y aparte la remuneración aún sin liquidar', function () {
    pagos_precios_y_reglas($this->admin, 1.70);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $this->admin);
    finanzas_sueldos($this->admin);
    pagos_trabajador($this->admin);
    finanzas_entrega(fixture_proveedor(fixture_zona()), 100, '2026-10-28 08:00:00');
    app(SincronizarPagosProveedor::class)->execute();

    $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSeeText(['Pagos pendientes', 'Saldo pendiente acumulado al 28/10/2026', 'S/ 170.00 vencen hoy', 'S/ 1,866.76 acumulados pendientes de liquidar'])
        ->assertDontSeeText(['Resumen de obligaciones', 'Devengado'])
        // El devengo provisional del mes en curso no se suma como obligación reconocida.
        ->assertSeeTextInOrder(['Pagos pendientes', 'S/ 170.00', 'Saldo pendiente acumulado'])
        ->assertSeeTextInOrder(['Proveedores', '1 obligación por pagar', 'S/ 170.00'])
        ->assertSeeTextInOrder(['Trabajadores', '0 obligaciones por pagar', 'S/ 1,866.76 acumulados pendientes de liquidar', 'S/ 0.00']);

    expect(finanzas('hoy')->totales)->toMatchArray(['pendienteReconocido' => 170.0, 'venceEnPeriodo' => 170.0]);
});
