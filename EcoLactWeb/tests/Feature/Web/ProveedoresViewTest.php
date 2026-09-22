<?php

use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Application\Proveedor\ActualizarProveedor;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ParametroCalidadModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla principal de proveedores muestra los KPIs y filtra por estado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    $activo = fixture_proveedor($zona, '11112222');
    $inactivo = fixture_proveedor($zona, '33334444');
    $inactivo->update(['estado' => 'INACTIVO']);

    $response = $this->actingAs($admin)->get(route('admin.proveedores.index', ['estado' => 'INACTIVO']));

    $response->assertOk();
    $response->assertSee('Proveedores activos');
    $response->assertSee('33334444');
});

it('el perfil del proveedor muestra sus entregas, análisis, liquidaciones, compras y zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_parametro('GRASA');
    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    fixture_regla_calidad(ParametroCalidadModel::where('codigo', 'GRASA')->first()->id, 3.0, 6.0, 'OBSERVADA');
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $analisis = app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entrega->id, valores: ['GRASA' => 3.5]);
    app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $admin->id);
    app(ValorizarEntrega::class)->execute($entrega->id);
    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: DateTimeImmutable::createFromInterface($entrega->fecha_hora)->modify('-1 day'),
        fechaFin: DateTimeImmutable::createFromInterface($entrega->fecha_hora)->modify('+1 day'),
        generadoPorUserId: $admin->id,
    );
    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );
    app(ActualizarProveedor::class)->execute(proveedorId: $proveedor->id, nuevaZonaId: fixture_zona('Otra Zona')->id, motivoCambioZona: 'Reasignación');

    $response = $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id));

    $response->assertOk();
    $response->assertSee('Resumen');
    $response->assertSee('30.00 L');
    $response->assertSee('Queso Paria', false);
    $response->assertSee('Reasignación');
});
