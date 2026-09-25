<?php

use App\Application\Dashboard\Contracts\DashboardAdministradorQuery;
use App\Application\Dashboard\DashboardAdministradorData;
use App\Application\Dashboard\DashboardPeriodo;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/DashboardFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * "Hoy" es el jueves 10/09/2026: semana calendario 07/09-13/09, mes 01/09-30/09.
 * Semanas de pago de proveedores (jueves a miércoles): la del 03/09 se paga hoy.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00', config('app.timezone')));
});

function dashboard_admin(string $periodo): DashboardAdministradorData
{
    return app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver($periodo));
}

it('devuelve ceros, bloques vacíos e intervalos sin datos inventados con la base vacía', function () {
    $data = dashboard_admin('semana');

    expect($data->acopio)->toBe(['litros' => 0.0, 'entregas' => 0, 'proveedores' => 0]);
    expect($data->produccion)->toBe(['total' => 0, 'porEstado' => []]);
    expect($data->ventas)->toBe(['importe' => 0.0, 'ventas' => 0, 'contado' => 0.0, 'descuentoPlanilla' => 0.0]);
    expect($data->pagos['total'])->toBe(0.0);
    expect($data->pagos['obligaciones'])->toBe(0);
    expect($data->acopioPorZona)->toBe([]);
    expect($data->lotesRecientes)->toBe([]);
    expect($data->comunicados)->toBe(['publicados' => 0, 'recientes' => []]);
    expect($data->porAtender['analisisPorConfirmar'])->toBe(0);
    expect($data->porAtender['lotesEnProceso'])->toBe(0);
    expect(array_column($data->acopioPorIntervalo, 'litros'))->toBe([0.0, 0.0, 0.0, 0.0, null, null, null]);
    expect(array_column($data->acopioPorIntervalo, 'futuro'))->toBe([false, false, false, false, true, true, true]);
});

it('suma solo entregas válidas dentro de cada período y distingue hoy, semana y mes', function () {
    dashboard_entrega('2026-08-31 23:59:59', 1000);
    dashboard_entrega('2026-09-06 23:59:59', 100);
    dashboard_entrega('2026-09-07 00:00:00', 10.25);
    dashboard_entrega('2026-09-10 00:00:00', 20.5);
    dashboard_entrega('2026-09-10 09:30:00', 4.25);
    dashboard_entrega('2026-09-10 09:00:00', 900)->update(['estado' => 'ANULADA']);

    expect(dashboard_admin('hoy')->acopio)->toBe(['litros' => 24.75, 'entregas' => 2, 'proveedores' => 2]);
    expect(dashboard_admin('semana')->acopio['litros'])->toBe(35.0);
    expect(dashboard_admin('mes')->acopio['litros'])->toBe(135.0);
});

it('cuenta proveedores distintos aunque entreguen varias veces', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    fixture_entrega($proveedor, $acopiador, 10, new DateTimeImmutable('2026-09-10 06:00:00'));
    fixture_entrega($proveedor, $acopiador, 15, new DateTimeImmutable('2026-09-10 07:00:00'));

    expect(dashboard_admin('hoy')->acopio)->toBe(['litros' => 25.0, 'entregas' => 2, 'proveedores' => 1]);
});

it('agrupa el gráfico por hora en hoy y por día en semana y mes con totales iguales a la tarjeta', function () {
    dashboard_entrega('2026-09-08 07:00:00', 12);
    dashboard_entrega('2026-09-10 06:10:00', 20.25);
    dashboard_entrega('2026-09-10 06:50:00', 5);
    dashboard_entrega('2026-09-10 11:00:00', 30)->update(['estado' => 'ANULADA']);

    $hoy = dashboard_admin('hoy');
    $semana = dashboard_admin('semana');
    $mes = dashboard_admin('mes');

    expect($hoy->acopioPorIntervalo)->toHaveCount(24);
    expect($hoy->acopioPorIntervalo[6])->toMatchArray(['clave' => '06', 'litros' => 25.25, 'entregas' => 2, 'futuro' => false]);
    expect($hoy->acopioPorIntervalo[13])->toMatchArray(['futuro' => true, 'litros' => null]);
    expect($semana->acopioPorIntervalo)->toHaveCount(7);
    expect($semana->acopioPorIntervalo[1])->toMatchArray(['clave' => '2026-09-08', 'litros' => 12.0, 'entregas' => 1]);
    expect($semana->acopioPorIntervalo[2])->toMatchArray(['clave' => '2026-09-09', 'litros' => 0.0, 'entregas' => 0, 'futuro' => false]);
    expect($mes->acopioPorIntervalo)->toHaveCount(30);
    expect($mes->acopioPorIntervalo[29])->toMatchArray(['clave' => '2026-09-30', 'futuro' => true, 'litros' => null]);

    foreach ([$hoy, $semana, $mes] as $data) {
        expect(round(array_sum(array_column($data->acopioPorIntervalo, 'litros')), 2))->toBe($data->acopio['litros']);
    }
});

it('ordena zonas de mayor a menor y suma las menores en Otras sin perder litros', function () {
    foreach ([70, 10, 60, 20, 50, 30, 40, 5] as $indice => $litros) {
        dashboard_entrega('2026-09-10 0'.($indice + 1).':00:00', $litros)->zona->update(['nombre' => 'Zona '.$litros]);
    }
    dashboard_entrega('2026-09-10 10:00:00', 500)->update(['estado' => 'ANULADA']);

    $data = dashboard_admin('hoy');

    expect(array_column($data->acopioPorZona, 'nombre'))->toBe(['Zona 70', 'Zona 60', 'Zona 50', 'Zona 40', 'Zona 30', 'Otras 3 zonas']);
    expect($data->acopioPorZona[5])->toBe(['nombre' => 'Otras 3 zonas', 'litros' => 35.0, 'otras' => true]);
    expect(array_sum(array_column($data->acopioPorZona, 'litros')))->toBe($data->acopio['litros']);
});

it('suma ventas de contado y descuento en planilla del período sin anuladas', function () {
    dashboard_venta('2026-09-10 08:00:00', '25.50');
    dashboard_venta('2026-09-10 09:00:00', '14.50', 'DESCUENTO_PLANILLA');
    dashboard_venta('2026-09-10 10:00:00', '999.00', estado: 'ANULADA');
    dashboard_venta('2026-09-09 23:59:59', '300.00');

    expect(dashboard_admin('hoy')->ventas)->toBe(['importe' => 40.0, 'ventas' => 2, 'contado' => 25.5, 'descuentoPlanilla' => 14.5]);
    expect(dashboard_admin('semana')->ventas['importe'])->toBe(340.0);
});

it('asigna pagos pendientes por fecha programada y tipo de beneficiario', function () {
    dashboard_obligacion_proveedor('2026-08-20', '1.00');   // se pagaba el 27/08: atrasado
    dashboard_obligacion_proveedor('2026-08-27', '10.00');  // se paga el 03/09
    dashboard_obligacion_proveedor('2026-09-03', '100.00'); // se paga hoy
    dashboard_obligacion_proveedor('2026-09-03', '5000.00', 'PAGADO');
    dashboard_obligacion_proveedor('2026-09-10', '1000.00'); // se paga el 17/09
    dashboard_obligacion_proveedor('2026-09-24', '7.00');   // se paga el 01/10
    dashboard_obligacion_trabajador('2026-08', '2026-09-01', '1200.00');
    dashboard_obligacion_trabajador('2026-07', '2026-08-01', '1300.00', 'PAGADO');
    dashboard_obligacion_trabajador('2026-09', null, '400.00', 'EN_CURSO');
    dashboard_obligacion_trabajador('2026-06', null, '50.00');

    $hoy = dashboard_admin('hoy')->pagos;
    $semana = dashboard_admin('semana')->pagos;
    $mes = dashboard_admin('mes')->pagos;

    expect($hoy['proveedores'])->toBe(['importe' => 100.0, 'obligaciones' => 1, 'semana' => '2026-09-03']);
    expect($hoy['trabajadores'])->toBe(['importe' => 0.0, 'obligaciones' => 0, 'mes' => null]);
    expect($hoy['atrasados'])->toBe(['importe' => 1211.0, 'obligaciones' => 3]);
    expect($semana['proveedores']['importe'])->toBe(100.0);
    expect($semana['proveedores']['semana'])->toBe('2026-09-03');
    expect($mes['proveedores'])->toBe(['importe' => 1110.0, 'obligaciones' => 3, 'semana' => null]);
    expect($mes['trabajadores'])->toBe(['importe' => 1200.0, 'obligaciones' => 1, 'mes' => '2026-08']);
    expect($mes['atrasados'])->toBe(['importe' => 1.0, 'obligaciones' => 1]);
    expect($mes['sinFecha'])->toBe(['importe' => 50.0, 'obligaciones' => 1]);

    foreach ([$hoy, $semana, $mes] as $pagos) {
        expect($pagos['total'])->toBe(round($pagos['proveedores']['importe'] + $pagos['trabajadores']['importe'], 2));
        expect($pagos['obligaciones'])->toBe($pagos['proveedores']['obligaciones'] + $pagos['trabajadores']['obligaciones']);
    }
});

it('cuenta lotes iniciados en el período con desglose coherente y todos sus productos', function () {
    dashboard_lote('ANTERIOR', '2026-09-06 23:59:59');
    dashboard_lote('EN-CURSO', '2026-09-07 08:00:00');
    $ultimo = dashboard_lote('TERMINADO', '2026-09-10 07:00:00', 'FINALIZADO');
    foreach ([['Yogur', 'L'], ['Queso fresco', 'kg']] as [$nombre, $unidad]) {
        ProduccionDetalleModel::create(['lote_produccion_id' => $ultimo->id, 'producto_id' => fixture_producto($nombre, $unidad)->id, 'cantidad_planificada' => 10, 'cantidad_obtenida' => 10]);
    }

    $semana = dashboard_admin('semana');

    expect($semana->produccion)->toBe(['total' => 2, 'porEstado' => ['EN_PROCESO' => 1, 'FINALIZADO' => 1]]);
    expect($semana->porAtender['lotesEnProceso'])->toBe(1);
    expect(array_column($semana->lotesRecientes, 'codigo'))->toBe(['TERMINADO', 'EN-CURSO']);
    expect($semana->lotesRecientes[0]['productos'])->toBe(['Queso fresco', 'Yogur']);
    expect($semana->lotesRecientes[1]['productos'])->toBe([]);
    expect(dashboard_admin('hoy')->produccion['total'])->toBe(1);
});

it('cuenta análisis en borrador solo de entregas válidas del período', function () {
    foreach (['2026-09-10 08:00:00', '2026-09-09 08:00:00'] as $fecha) {
        AnalisisCalidadModel::create(['entrega_id' => dashboard_entrega($fecha)->id, 'estado' => 'BORRADOR', 'fecha_analisis' => $fecha]);
    }
    $anulada = dashboard_entrega('2026-09-10 09:00:00');
    AnalisisCalidadModel::create(['entrega_id' => $anulada->id, 'estado' => 'BORRADOR', 'fecha_analisis' => '2026-09-10 09:00:00']);
    $anulada->update(['estado' => 'ANULADA']);
    dashboard_entrega('2026-09-10 10:00:00', 30, 'ACEPTADA');

    expect(dashboard_admin('hoy')->porAtender['analisisPorConfirmar'])->toBe(1);
    expect(dashboard_admin('semana')->porAtender['analisisPorConfirmar'])->toBe(2);
});

it('presenta existencias y alertas de insumos como saldo actual, igual en cualquier período', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(320.5, $admin->id);
    fixture_insumo('Cuajo', 'L', stock: 2, minimo: 5, userId: $admin->id);
    fixture_insumo('Sal', 'kg', stock: 0, minimo: 3, userId: $admin->id);
    fixture_insumo('Envases', 'unidad', stock: 100, minimo: 10, userId: $admin->id);
    $queso = fixture_producto('Queso fresco', 'kg');
    fixture_ingreso_producto($queso->id, 12.5, $admin->id);
    fixture_producto('Mantequilla', 'kg');

    $hoy = dashboard_admin('hoy');

    expect($hoy->existencias)->toMatchArray([
        'leche' => ['cantidad' => 320.5, 'unidad' => 'L'],
        'productos' => [['id' => $queso->id, 'nombre' => 'Queso fresco', 'cantidad' => 12.5, 'unidad' => 'kg']],
        'productosConStock' => 1,
        'insumosBajoMinimo' => 1,
        'insumosSinStock' => 1,
    ]);
    expect(dashboard_admin('mes')->existencias)->toBe($hoy->existencias);
});

it('mide la asistencia del día contra el personal aplicable y el rango con jornadas registradas', function () {
    $presente = dashboard_trabajador();
    $falta = dashboard_trabajador();
    dashboard_trabajador();
    dashboard_trabajador(ingreso: '2026-09-11');
    dashboard_trabajador(salida: '2026-09-09');
    dashboard_trabajador(estado: 'INACTIVO');
    dashboard_jornada($presente->id, '2026-09-10');
    dashboard_jornada($falta->id, '2026-09-10', 'FALTA');
    dashboard_jornada($presente->id, '2026-09-08');
    dashboard_jornada($presente->id, '2026-09-01');

    expect(dashboard_admin('hoy')->asistencia)->toBe(['modo' => 'dia', 'personal' => 3, 'presentes' => 1, 'faltas' => 1, 'sinMarcar' => 1, 'trabajadores' => 1, 'dias' => 1]);
    expect(dashboard_admin('semana')->asistencia)->toBe(['modo' => 'rango', 'personal' => 0, 'presentes' => 2, 'faltas' => 1, 'sinMarcar' => 0, 'trabajadores' => 2, 'dias' => 4]);
    expect(dashboard_admin('mes')->asistencia)->toMatchArray(['presentes' => 3, 'faltas' => 1, 'dias' => 10]);
});

it('mide la asistencia de hoy sin importar el período elegido', function () {
    $presente = dashboard_trabajador();
    dashboard_trabajador();
    dashboard_jornada($presente->id, '2026-09-10');
    dashboard_jornada($presente->id, '2026-09-08');

    $hoy = ['modo' => 'dia', 'personal' => 2, 'presentes' => 1, 'faltas' => 0, 'sinMarcar' => 1, 'trabajadores' => 1, 'dias' => 1];
    expect(dashboard_admin('hoy')->asistenciaHoy)->toBe($hoy);
    expect(dashboard_admin('semana')->asistenciaHoy)->toBe($hoy);
    expect(dashboard_admin('mes')->asistenciaHoy)->toBe($hoy);
});

it('lista comunicados publicados del período sin borradores ni programados a futuro', function () {
    dashboard_comunicado('Reunión de proveedores', '2026-09-10 08:00:00');
    dashboard_comunicado('Borrador', '2026-09-10 09:00:00', 'BORRADOR');
    dashboard_comunicado('Programado', '2026-09-10 18:00:00');
    dashboard_comunicado('Del lunes', '2026-09-07 08:00:00');

    expect(dashboard_admin('hoy')->comunicados['publicados'])->toBe(1);
    expect(array_column(dashboard_admin('semana')->comunicados['recientes'], 'titulo'))->toBe(['Reunión de proveedores', 'Del lunes']);
});

it('solo ejecuta lecturas y mantiene constante el número de consultas', function () {
    dashboard_entrega('2026-09-10 08:00:00', 20);
    dashboard_lote('L-1', '2026-09-10 08:00:00');
    DB::enableQueryLog();
    dashboard_admin('mes');
    $iniciales = count(DB::getQueryLog());
    foreach (range(1, 6) as $numero) {
        dashboard_entrega('2026-09-0'.$numero.' 08:00:00', 10);
        dashboard_lote('L-extra-'.$numero, '2026-09-0'.$numero.' 09:00:00');
    }
    DB::flushQueryLog();

    dashboard_admin('mes');

    $consultas = DB::getQueryLog();
    DB::disableQueryLog();
    expect($consultas)->toHaveCount($iniciales);
    foreach ($consultas as $consulta) {
        expect(strtolower(ltrim($consulta['query'])))->toStartWith('select');
    }
});
