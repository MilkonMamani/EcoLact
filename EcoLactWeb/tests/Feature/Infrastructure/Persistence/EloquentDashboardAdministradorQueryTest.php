<?php

use App\Application\Dashboard\Contracts\DashboardAdministradorQuery;
use App\Application\Dashboard\DashboardPeriodo;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\ComunicadoDestinatarioModel;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/DashboardFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00', 'UTC'));
});

it('devuelve ceros y bloques vacíos con una base de datos vacía', function () {
    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->kpis)->toMatchArray([
        'totalAcopiado' => 0.0, 'proveedoresAtendidos' => 0, 'proveedoresActivos' => 0,
        'porcentajeAtendidos' => 0.0, 'calidadAceptada' => 0.0, 'analisisConfirmados' => 0,
        'entregasObservadas' => 0, 'totalValorizado' => 0.0, 'produccion' => 0,
    ]);
    expect($data->litrosPorDia)->toBe([]);
    expect($data->rendimientoZonas)->toBe([]);
    expect($data->entregasRecientes)->toBe([]);
    expect($data->actividadReciente)->toBe([]);
    expect($data->produccionReciente)->toBeNull();
    expect($data->comunicaciones)->toBe(['publicados' => 0, 'destinatarios' => 0, 'leidos' => 0, 'tasaLectura' => null, 'recientes' => []]);
});

it('suma litros válidos e incluye los extremos del día excluyendo anuladas y fechas ajenas', function () {
    dashboard_entrega('2026-09-10 00:00:00', 20.15);
    dashboard_entrega('2026-09-10 23:59:59', 30.25, 'ACEPTADA');
    dashboard_entrega('2026-09-10 10:00:00', 900)->update(['estado' => 'ANULADA']);
    dashboard_entrega('2026-09-09 23:59:59', 1000);
    dashboard_entrega('2026-09-11 00:00:00', 2000);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->kpis['totalAcopiado'])->toBe(50.4);
    expect($data->litrosPorDia)->toHaveCount(1);
    expect($data->litrosPorDia[0])->toMatchArray(['fecha' => '2026-09-10', 'litros' => 50.4, 'entregas' => 2]);
});

it('cuenta proveedores distintos y usa los activos actuales como denominador', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    fixture_proveedor($zona);
    fixture_proveedor($zona)->update(['estado' => 'INACTIVO']);
    fixture_entrega($proveedor, $acopiador, 10, new DateTimeImmutable('2026-09-10 08:00:00'));
    fixture_entrega($proveedor, $acopiador, 20, new DateTimeImmutable('2026-09-10 10:00:00'));

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->kpis)->toMatchArray(['proveedoresAtendidos' => 1, 'proveedoresActivos' => 2, 'porcentajeAtendidos' => 50.0]);
});

it('muestra cero sobre cero si ya no quedan proveedores activos', function () {
    $entrega = dashboard_entrega('2026-09-10 08:00:00');
    $entrega->proveedor->update(['estado' => 'INACTIVO']);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->kpis)->toMatchArray(['proveedoresAtendidos' => 0, 'proveedoresActivos' => 0, 'porcentajeAtendidos' => 0.0, 'totalAcopiado' => 30.0]);
});

it('calcula aceptación observadas y distribución solo con confirmados según fecha de análisis', function () {
    dashboard_entrega('2026-09-10 08:00:00', 20, 'ACEPTADA');
    dashboard_entrega('2026-09-10 09:00:00', 30, 'OBSERVADA');
    dashboard_entrega('2026-09-10 10:00:00', 40, 'RECHAZADA');
    $anterior = dashboard_entrega('2026-09-09 10:00:00', 10, 'ACEPTADA');
    $anterior->analisisCalidad->update(['fecha_analisis' => '2026-09-10 11:00:00']);
    dashboard_entrega('2026-09-09 11:00:00', 500, 'OBSERVADA');
    $borrador = dashboard_entrega('2026-09-10 11:00:00', 1000);
    AnalisisCalidadModel::create(['entrega_id' => $borrador->id, 'estado' => 'BORRADOR', 'resultado' => null, 'fecha_analisis' => '2026-09-10 11:00:00']);
    $borradorConResultado = dashboard_entrega('2026-09-10 11:30:00', 1000, 'OBSERVADA');
    $borradorConResultado->analisisCalidad->update(['estado' => 'BORRADOR']);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->kpis)->toMatchArray(['calidadAceptada' => 50.0, 'analisisConfirmados' => 4, 'entregasObservadas' => 1]);
    expect($data->distribucionCalidad)->toBe([
        ['resultado' => 'ACEPTADA', 'cantidad' => 2, 'porcentaje' => 50.0, 'litros' => 30.0],
        ['resultado' => 'OBSERVADA', 'cantidad' => 1, 'porcentaje' => 25.0, 'litros' => 30.0],
        ['resultado' => 'RECHAZADA', 'cantidad' => 1, 'porcentaje' => 25.0, 'litros' => 40.0],
    ]);
});

it('excluye litros anulados también de la distribución sin alterar el total de análisis confirmados', function () {
    dashboard_entrega('2026-09-10 10:00:00', 90, 'RECHAZADA')->update(['estado' => 'ANULADA']);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->kpis['totalAcopiado'])->toBe(0.0);
    expect($data->distribucionCalidad[2])->toBe(['resultado' => 'RECHAZADA', 'cantidad' => 1, 'porcentaje' => 100.0, 'litros' => 0.0]);
});

it('suma importes individuales con decimales y filtra por fecha de cálculo', function () {
    dashboard_valorizacion(dashboard_entrega('2026-09-09 08:00:00', 30, 'ACEPTADA'), '60.10', '2026-09-10 00:00:00');
    dashboard_valorizacion(dashboard_entrega('2026-09-10 08:00:00', 30, 'OBSERVADA'), '45.20', '2026-09-10 23:59:59');
    dashboard_valorizacion(dashboard_entrega('2026-09-10 09:00:00', 30, 'ACEPTADA'), '500.00', '2026-09-11 00:00:00');

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->kpis['totalValorizado'])->toBe(105.3);
});

it('agrupa litros y número de entregas por día en orden cronológico', function () {
    dashboard_entrega('2026-09-10 08:00:00', 21.25);
    dashboard_entrega('2026-09-09 08:00:00', 10.10);
    dashboard_entrega('2026-09-09 14:00:00', 15.20);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('personalizado', '2026-09-09', '2026-09-10'));

    expect($data->litrosPorDia)->toHaveCount(2);
    expect($data->litrosPorDia[0])->toMatchArray(['fecha' => '2026-09-09', 'litros' => 25.3, 'entregas' => 2]);
    expect($data->litrosPorDia[1])->toMatchArray(['fecha' => '2026-09-10', 'litros' => 21.25, 'entregas' => 1]);
});

it('separa el rendimiento por zona histórica de la entrega e incluye zonas sin actividad', function () {
    $norte = dashboard_entrega('2026-09-10 08:00:00', 50, 'ACEPTADA');
    $sur = dashboard_entrega('2026-09-10 09:00:00', 20, 'RECHAZADA');
    $norte->zona->update(['nombre' => 'Pampa Norte']);
    $sur->zona->update(['nombre' => 'Valle Sur']);
    $vacia = fixture_zona('Pampa Alta');
    $norte->proveedor->update(['zona_id' => $sur->zona_id]);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));
    $zonas = collect($data->rendimientoZonas)->keyBy('id');

    expect($zonas[$norte->zona_id])->toMatchArray(['nombre' => 'Pampa Norte', 'litros' => 50.0, 'proveedores' => 1, 'calidad' => 100.0]);
    expect($zonas[$sur->zona_id])->toMatchArray(['nombre' => 'Valle Sur', 'litros' => 20.0, 'proveedores' => 1, 'calidad' => 0.0]);
    expect($zonas[$vacia->id])->toMatchArray(['litros' => 0.0, 'proveedores' => 0, 'confirmados' => 0]);
});

it('cuenta lotes del período y devuelve cantidades por producto con sus propias unidades', function () {
    dashboard_lote('ANTERIOR', '2026-09-09 23:59:59');
    dashboard_lote('EN-CURSO', '2026-09-10 00:00:00');
    $ultimo = dashboard_lote('TERMINADO', '2026-09-10 23:59:59', 'FINALIZADO');
    $queso = ProductoModel::create(['nombre' => 'Queso fresco', 'unidad_medida' => 'kg', 'estado' => 'ACTIVO']);
    $yogur = ProductoModel::create(['nombre' => 'Yogur', 'unidad_medida' => 'L', 'estado' => 'ACTIVO']);
    ProduccionDetalleModel::create(['lote_produccion_id' => $ultimo->id, 'producto_id' => $queso->id, 'cantidad_planificada' => 15.25, 'cantidad_obtenida' => 15.25]);
    ProduccionDetalleModel::create(['lote_produccion_id' => $ultimo->id, 'producto_id' => $yogur->id, 'cantidad_planificada' => 20, 'cantidad_obtenida' => 20]);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->kpis)->toMatchArray(['produccion' => 2, 'produccionPorEstado' => ['EN_PROCESO' => 1, 'FINALIZADO' => 1]]);
    expect($data->produccionReciente)->toMatchArray(['id' => $ultimo->id, 'codigo' => 'TERMINADO', 'litros' => 150.25]);
    expect($data->produccionReciente['productos'])->toBe([
        ['nombre' => 'Queso fresco', 'unidad' => 'kg', 'cantidad' => 15.25],
        ['nombre' => 'Yogur', 'unidad' => 'L', 'cantidad' => 20.0],
    ]);
});

it('calcula lecturas reales de publicados sin incluir borradores futuros ni otros períodos', function () {
    $comunicado = dashboard_comunicado('Reunión de proveedores', '2026-09-10 08:00:00');
    dashboard_comunicado('Borrador', '2026-09-10 09:00:00', 'BORRADOR');
    dashboard_comunicado('Futuro', '2026-09-10 13:00:00');
    dashboard_comunicado('Anterior', '2026-09-09 08:00:00');
    foreach ([true, false] as $leido) {
        ComunicadoDestinatarioModel::create(['comunicado_id' => $comunicado->id, 'user_id' => fixture_usuario('PROVEEDOR')->id, 'leido' => $leido]);
    }

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->comunicaciones)->toMatchArray(['publicados' => 1, 'destinatarios' => 2, 'leidos' => 1, 'tasaLectura' => 50.0]);
    expect($data->comunicaciones['recientes'][0]['titulo'])->toBe('Reunión de proveedores');
});

it('no inventa tasa de lectura para un comunicado sin destinatarios', function () {
    dashboard_comunicado('Aviso general', '2026-09-10 08:00:00');

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->comunicaciones)->toMatchArray(['publicados' => 1, 'destinatarios' => 0, 'tasaLectura' => null]);
});

it('ordena cinco eventos reales incluyendo entregas análisis liquidaciones pagos y comunicados', function () {
    $entrega = dashboard_entrega('2026-09-10 07:00:00', 30, 'ACEPTADA');
    $entrega->analisisCalidad->update(['fecha_analisis' => '2026-09-10 08:00:00']);
    $periodo = PeriodoLiquidacionModel::create(['fecha_inicio' => '2026-09-03', 'fecha_fin' => '2026-09-09', 'estado' => 'ABIERTO']);
    $liquidacion = LiquidacionModel::create([
        'periodo_id' => $periodo->id, 'proveedor_id' => $entrega->proveedor_id,
        'total_litros_entregados' => 30, 'total_litros_pagables' => 30, 'subtotal' => 60,
        'total_ajustes' => 0, 'total_pagar' => 60, 'estado' => 'PENDIENTE',
        'fecha_calculo' => '2026-09-10 09:00:00', 'generado_por_user_id' => $entrega->registrado_por_user_id,
        'precio_litro_aplicado' => 2.00, 'tuvo_incidencia' => false,
    ]);
    PagoProveedorModel::create([
        'liquidacion_id' => $liquidacion->id, 'fecha_pago' => '2026-09-10 10:00:00', 'monto' => 60,
        'estado' => 'REGISTRADO', 'registrado_por_user_id' => $entrega->registrado_por_user_id,
    ]);
    dashboard_comunicado('Aviso', '2026-09-10 11:00:00');
    dashboard_entrega('2026-09-09 10:00:00', 30);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect(array_column($data->actividadReciente, 'tipo'))->toBe(['comunicado', 'pago', 'liquidacion', 'analisis', 'entrega']);
    expect($data->kpis['totalValorizado'])->toBe(0.0);
    $this->assertDatabaseCount('auditorias', 0);
});

it('limita las entregas recientes a seis y mantiene las anuladas visibles con nombres reales', function () {
    $ultima = null;
    for ($hora = 1; $hora <= 8; $hora++) {
        $ultima = dashboard_entrega(sprintf('2026-09-10 %02d:00:00', $hora));
    }
    $ultima->update(['estado' => 'ANULADA']);
    $ultima->proveedor->update(['nombres' => 'Elena', 'apellidos' => 'Quispe']);
    $ultima->zona->update(['nombre' => 'Pampa Grande']);

    $data = app(DashboardAdministradorQuery::class)->obtener(DashboardPeriodo::resolver('hoy'));

    expect($data->entregasRecientes)->toHaveCount(6);
    expect($data->entregasRecientes[0])->toMatchArray(['id' => $ultima->id, 'estado' => 'ANULADA', 'proveedorNombre' => 'Elena Quispe', 'zonaNombre' => 'Pampa Grande', 'calidad' => null]);
    expect($data->actividadReciente)->toHaveCount(5);
});

it('mantiene constante el número de consultas al aumentar zonas y solo ejecuta lecturas', function () {
    dashboard_entrega('2026-09-10 08:00:00', 20, 'ACEPTADA');
    $query = app(DashboardAdministradorQuery::class);
    DB::enableQueryLog();
    $query->obtener(DashboardPeriodo::resolver('hoy'));
    $iniciales = count(DB::getQueryLog());
    DB::disableQueryLog();
    foreach (range(1, 15) as $numero) {
        fixture_zona('Zona adicional '.$numero);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();

    $query->obtener(DashboardPeriodo::resolver('hoy'));

    $consultas = DB::getQueryLog();
    DB::disableQueryLog();
    expect($consultas)->toHaveCount($iniciales);
    foreach ($consultas as $consulta) {
        expect(strtolower(ltrim($consulta['query'])))->toStartWith('select');
    }
});
