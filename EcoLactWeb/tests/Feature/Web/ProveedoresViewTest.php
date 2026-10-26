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

it('la pantalla principal de proveedores filtra por estado y muestra última entrega y litros totales', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    $activo = fixture_proveedor($zona, '11112222');
    $inactivo = fixture_proveedor($zona, '33334444');
    $inactivo->update(['estado' => 'INACTIVO']);

    $response = $this->actingAs($admin)->get(route('admin.proveedores.index', ['estado' => 'INACTIVO']));

    $response->assertOk();
    $response->assertSee('33334444');
    $response->assertDontSee('11112222');

    $acopiador = fixture_acopiador($zona);
    fixture_entrega($activo, $acopiador, 20.0, new DateTimeImmutable('2026-09-10 08:00'));
    fixture_entrega($activo, $acopiador, 15.5, new DateTimeImmutable('2026-09-12 08:00'));
    fixture_entrega($activo, $acopiador, 99.0, new DateTimeImmutable('2026-09-14 08:00'))->update(['estado' => 'ANULADA']);

    $this->get(route('admin.proveedores.index', ['q' => '11112222']))
        ->assertOk()
        ->assertSeeInOrder(['Nombre', 'DNI', 'Teléfono', 'Zona', 'Última entrega', 'Total (L)', 'Estado'])
        ->assertSeeInOrder(['11112222', 'Zona Norte', '12/09/2026', '35.50', 'Activo']);
});

it('el listado se pagina en el servidor y conserva los filtros en los enlaces', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    foreach (range(1, 13) as $i) {
        fixture_proveedor($zona, sprintf('7000%04d', $i));
    }

    $this->actingAs($admin)->get(route('admin.proveedores.index', ['estado' => 'ACTIVO']))
        ->assertOk()
        ->assertSee('Mostrando 1 a 12 de 13 proveedores')
        ->assertSee(e(route('admin.proveedores.index', ['estado' => 'ACTIVO', 'pagina' => 2])), false);
});

it('busca por teléfono y por código (#) además de nombre y DNI', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    $conTelefono = fixture_proveedor($zona, '11110000');
    $conTelefono->update(['telefono' => '951222333']);
    $otro = fixture_proveedor($zona, '22220000');

    $this->actingAs($admin)->get(route('admin.proveedores.index', ['q' => '951222']))
        ->assertSee('11110000')->assertDontSee('22220000');

    $this->get(route('admin.proveedores.index', ['q' => '#'.$otro->id]))
        ->assertSee('22220000')->assertDontSee('11110000');
});

it('el panel del proveedor muestra sus entregas y deja fuera de Compras las ventas al contado', function () {
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
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );
    app(ActualizarProveedor::class)->execute(proveedorId: $proveedor->id, nuevaZonaId: fixture_zona('Otra Zona')->id, motivoCambioZona: 'Reasignación');

    $response = $this->actingAs($admin)->get(route('admin.proveedores.index', ['proveedor' => $proveedor->id]));

    $response->assertOk();
    $response->assertSee('Resumen');
    $response->assertSee('30.00');
    $response->assertSee('Otra Zona');
    $response->assertDontSee('Queso Paria');
});
