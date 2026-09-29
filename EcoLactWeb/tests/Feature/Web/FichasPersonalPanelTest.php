<?php

use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';
require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * Analistas de calidad, encargados de planta y personal de ventas usan la pantalla
 * "listado + panel" de Acopiadores. Cada panel muestra solo la actividad real de ese
 * trabajador, ligada por el usuario que la hizo.
 */

function fichas_venta(User $vendedor, string $tipoCliente, float $total, string $fecha = '2026-09-20 10:00:00'): VentaProductoModel
{
    $proveedor = $tipoCliente === 'PROVEEDOR' ? fixture_proveedor(fixture_zona()) : null;
    $proveedor?->update(['nombres' => 'Milkon', 'apellidos' => 'Mamani']);

    return VentaProductoModel::create([
        'producto_id' => fixture_producto('Queso '.uniqid(), 'kg')->id,
        'proveedor_id' => $proveedor?->id,
        'tipo_cliente' => $tipoCliente,
        'cantidad' => 1,
        'precio_unitario' => $total,
        'importe_total' => $total,
        'forma_pago' => $proveedor !== null ? 'DESCUENTO_PLANILLA' : 'CONTADO',
        'estado' => 'REGISTRADA',
        'fecha' => $fecha,
        'registrado_por_user_id' => $vendedor->id,
    ]);
}

it('las tres pantallas comparten el listado de Acopiadores con su columna de actividad y sin columna #', function (string $rol, string $ruta, string $columna) {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal($rol, 'Rosa');

    $this->actingAs($admin)->get(route($ruta))
        ->assertOk()
        ->assertSee('data-fichas-page', false)
        ->assertSeeInOrder(['Nombre', 'DNI', 'Teléfono', $columna, 'Estado'])
        ->assertSee('data-estado-switch', false)
        ->assertSee('Rosa Apellido')
        ->assertDontSee('<th scope="col">#</th>', false)
        ->assertDontSee('name="zona_id"', false);
})->with([
    ['CALIDAD', 'admin.analistas-calidad.index', 'Análisis'],
    ['ENCARGADO_PLANTA', 'admin.encargados-planta.index', 'Producciones'],
    ['VENTAS', 'admin.personal-ventas.index', 'Ventas'],
]);

it('busca por nombre o DNI y filtra por estado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ana = pagos_personal('CALIDAD', 'Ana');
    $luis = pagos_personal('CALIDAD', 'Luis', estado: 'INACTIVO');

    $this->actingAs($admin);

    $this->get(route('admin.analistas-calidad.index', ['q' => 'Ana']))->assertSee($ana->analistaCalidad->dni)->assertDontSee($luis->analistaCalidad->dni);
    $this->get(route('admin.analistas-calidad.index', ['q' => $luis->analistaCalidad->dni]))->assertSee('Luis Apellido')->assertDontSee('Ana Apellido');
    $this->get(route('admin.analistas-calidad.index', ['estado' => 'INACTIVO']))->assertSee('Luis Apellido')->assertDontSee('Ana Apellido');
});

it('el analista solo ve los análisis que él confirmó, con sus métricas por resultado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    $proveedor->update(['nombres' => 'Milkon', 'apellidos' => 'Mamani']);
    $david = pagos_personal('CALIDAD', 'David');
    $otro = pagos_personal('CALIDAD', 'Otro');

    $propio = fixture_analisis_confirmado(fixture_entrega($proveedor, $acopiador, 20), 'ACEPTADA', $david->id);
    fixture_analisis_confirmado(fixture_entrega($proveedor, $acopiador, 20), 'OBSERVADA', $david->id);
    $ajeno = fixture_analisis_confirmado(fixture_entrega($proveedor, $acopiador, 20), 'RECHAZADA', $otro->id);

    $this->actingAs($admin)->get(route('admin.analistas-calidad.panel', $david->analistaCalidad->id))
        ->assertOk()
        ->assertSeeInOrder(['Resumen', 'Análisis', 'Asistencia', 'Pagos'])
        ->assertDontSee('>Zona<', false)
        ->assertSeeInOrder(['Análisis realizados', '2', 'Aceptados', '1', 'Observados', '1', 'Rechazados', '0'])
        ->assertSeeInOrder(['Fecha', 'Entrega', 'Proveedor', 'Resultado', 'Estado', 'Ver'])
        ->assertSee('Entrega #'.$propio->entrega_id)
        ->assertSee('Milkon Mamani')
        ->assertSee(route('admin.analisis-calidad.show', $propio->id), false)
        ->assertDontSee(route('admin.analisis-calidad.show', $ajeno->id), false);

    $this->get(route('admin.analistas-calidad.index'))->assertSeeInOrder(['David Apellido', '2', 'Otro Apellido', '1']);
});

it('el encargado solo ve los lotes que registró, con la leche real consumida y el producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $abel = pagos_personal('ENCARGADO_PLANTA', 'Abel');
    $otro = pagos_personal('ENCARGADO_PLANTA', 'Otro');

    $propio = fixture_lote(80, $abel->id);
    fixture_lote(20, $abel->id, 'FINALIZADO');
    $ajeno = fixture_lote(50, $otro->id);
    ProduccionDetalleModel::create(['lote_produccion_id' => $propio->id(), 'producto_id' => fixture_producto('Queso Paria')->id, 'cantidad_planificada' => 10]);

    $this->actingAs($admin)->get(route('admin.encargados-planta.panel', $abel->encargadoPlanta->id))
        ->assertOk()
        ->assertSeeInOrder(['Resumen', 'Producción', 'Asistencia', 'Pagos'])
        ->assertSeeInOrder(['Producciones registradas', '2', 'En proceso', '1', 'Finalizadas', '1', 'Leche procesada', '100.00 L'])
        ->assertSeeInOrder(['Fecha', 'Lote', 'Producto', 'Leche', 'Estado', 'Ver'])
        ->assertSeeInOrder([$propio->codigo(), 'Queso Paria', '80.00 L', 'En proceso'])
        ->assertSee(route('admin.produccion.show', $propio->id()), false)
        ->assertDontSee(route('admin.produccion.show', $ajeno->id()), false);
});

it('el vendedor solo ve sus ventas y puede filtrarlas por tipo de cliente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $rosa = pagos_personal('VENTAS', 'Rosa');
    $otro = pagos_personal('VENTAS', 'Otro');

    $aProveedor = fichas_venta($rosa, 'PROVEEDOR', 28);
    $alPublico = fichas_venta($rosa, 'PUBLICO', 12);
    $ajena = fichas_venta($otro, 'MAYORISTA', 500);
    $panel = route('admin.personal-ventas.panel', $rosa->personalVentas->id);

    $this->actingAs($admin)->get($panel)
        ->assertOk()
        ->assertSeeInOrder(['Resumen', 'Ventas', 'Asistencia', 'Pagos'])
        ->assertSeeInOrder(['Ventas registradas', '2', 'Total vendido', 'S/ 40.00', 'A proveedores', '1', 'Público general', '1', 'Mayoristas', '0'])
        ->assertSeeInOrder(['Todos', 'Proveedor', 'Público general', 'Mayorista'])
        ->assertSee('Milkon Mamani')
        ->assertSee('Descuento semanal')
        ->assertSee(route('admin.ventas.show', $aProveedor->id), false)
        ->assertSee(route('admin.ventas.show', $alPublico->id), false)
        ->assertDontSee(route('admin.ventas.show', $ajena->id), false);

    $this->get($panel.'?tipo_cliente=PROVEEDOR')
        ->assertSee(route('admin.ventas.show', $aProveedor->id), false)
        ->assertDontSee(route('admin.ventas.show', $alPublico->id), false);
});

it('el resumen muestra el QR de asistencia del propio trabajador y su fecha de ingreso', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $abel = pagos_personal('ENCARGADO_PLANTA', 'Abel', '2026-09-14');
    $qr = pagos_trabajador($abel)->qr_token;
    $otro = pagos_trabajador(pagos_personal('ENCARGADO_PLANTA', 'Otro'))->qr_token;

    $this->actingAs($admin)->get(route('admin.encargados-planta.panel', $abel->encargadoPlanta->id))
        ->assertSee(base64_encode($qr), false)
        ->assertDontSee(base64_encode($otro), false)
        ->assertSeeInOrder(['Fecha de ingreso', '14/09/2026']);
    expect(TrabajadorModel::where('user_id', $abel->id)->value('qr_token'))->toBe($qr);
});

it('el switch desactiva y reactiva sin pedir zona, respondiendo JSON', function (string $rol, string $ruta, string $relacion) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ficha = pagos_personal($rol, 'Rosa')->{$relacion};

    $this->actingAs($admin)->patchJson(route($ruta.'.desactivar', $ficha->id))
        ->assertOk()
        ->assertJsonPath('ficha.estado', 'INACTIVO');
    $this->patchJson(route($ruta.'.reactivar', $ficha->id))
        ->assertOk()
        ->assertJsonPath('ficha', ['id' => $ficha->id, 'estado' => 'ACTIVO']);
    $this->patchJson(route($ruta.'.reactivar', $ficha->id))->assertStatus(422);

    $this->get(route($ruta.'.index'))->assertDontSee('id="reactivar-zona-id"', false);
})->with([
    ['CALIDAD', 'admin.analistas-calidad', 'analistaCalidad'],
    ['ENCARGADO_PLANTA', 'admin.encargados-planta', 'encargadoPlanta'],
    ['VENTAS', 'admin.personal-ventas', 'personalVentas'],
]);
