<?php

use App\Application\Caja\RegistrarMovimientoCaja;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Application\Produccion\GuardarPresentacionInsumo;
use App\Application\Produccion\RegistrarCompraInsumos;
use App\Application\Ventas\RegistrarComprador;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Domain\Caja\MovimientoCajaRepository;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Pago\PagoRealizadoRepository;
use App\Domain\Shared\Enums\MetodoPago;
use App\Domain\Ventas\VentaProducto;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\mock;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/** Venta al contado de 10 unidades a S/ 1,000 (S/ 10,000) a "Distribuidora ABC". */
function caja_venta(User $usuario, string $formaPago = 'CONTADO', float $precio = 1000.0, float $cantidad = 10.0): int
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria '.uniqid(), 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    $tipoCliente = $formaPago === 'CONTADO' ? 'PUBLICO' : 'PROVEEDOR';
    app(RegistrarProductoPrecio::class)->execute($producto->id, $tipoCliente, $precio, $admin->id);
    fixture_ingreso_producto($producto->id, $cantidad, $admin->id);

    return (int) app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: $cantidad,
        formaPago: $formaPago,
        registradoPorUserId: $usuario->id,
        compradorId: $formaPago === 'CONTADO' ? app(RegistrarComprador::class)->execute('Distribuidora ABC')->id() : null,
    )->id();
}

/** Pago PENDIENTE de un proveedor (semana ya cerrada) o de un trabajador (mes anterior). */
function caja_obligacion(string $tipo, float $monto, string $nombre): ObligacionPagoModel
{
    if ($tipo === 'PROVEEDOR') {
        $proveedor = fixture_proveedor(fixture_zona());
        $inicio = now()->subDays(14)->startOfDay();

        return ObligacionPagoModel::create([
            'tipo_beneficiario' => 'PROVEEDOR', 'proveedor_id' => $proveedor->id, 'beneficiario_nombre' => $nombre,
            'periodo_inicio' => $inicio->format('Y-m-d'), 'periodo_fin' => $inicio->addDays(6)->format('Y-m-d'),
            'periodicidad' => 'SEMANAL', 'concepto' => 'Pago semanal a proveedor', 'monto_calculado' => $monto,
            'estado' => 'PENDIENTE', 'clave_unicidad' => 'PROVEEDOR:'.$proveedor->id.':'.$inicio->format('Y-m-d'),
        ]);
    }

    $usuario = fixture_usuario('ACOPIADOR');
    $inicio = now()->subMonthNoOverflow()->startOfMonth();

    return ObligacionPagoModel::create([
        'tipo_beneficiario' => 'PERSONAL', 'user_id' => $usuario->id, 'rol_id' => $usuario->role_id, 'beneficiario_nombre' => $nombre,
        'periodo_inicio' => $inicio->format('Y-m-d'), 'periodo_fin' => $inicio->endOfMonth()->format('Y-m-d'),
        'periodicidad' => 'MENSUAL', 'concepto' => 'Sueldo mensual', 'monto_calculado' => $monto,
        'estado' => 'PENDIENTE', 'clave_unicidad' => 'PERSONAL:'.$usuario->id.':'.$inicio->format('Y-m-d'),
    ]);
}

function caja_pagar(ObligacionPagoModel $obligacion, User $usuario): void
{
    app(RegistrarPagoObligacion::class)->execute(
        obligacionId: $obligacion->id,
        metodoPago: MetodoPago::EFECTIVO,
        numeroOperacion: null,
        fechaPago: DateTimeImmutable::createFromInterface(now()),
        observacion: null,
        comprobante: null,
        registradoPorUserId: $usuario->id,
    );
}

/** Compra confirmada de 2 sacos de sal con boleta B001-058 por el total indicado. */
function caja_compra(User $usuario, ?float $total = 450.0): int
{
    $sal = fixture_insumo('Sal', 'g', 0.0, 0.0, $usuario->id);
    $saco = app(GuardarPresentacionInsumo::class)->execute($sal->id(), 'Saco', 25, 'kg');

    return (int) app(RegistrarCompraInsumos::class)->execute(
        [['insumo_id' => $sal->id(), 'presentacion_id' => $saco->id(), 'cantidad' => 2]],
        ['numero_comprobante' => 'B001-058', 'total' => $total],
        $usuario->id,
    )->id();
}

it('cada módulo alimenta la caja sola y el saldo es entradas menos salidas (S/ 7,730.00)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $vendedor = fixture_usuario('VENTAS');

    $ventaId = caja_venta($vendedor);
    caja_pagar(caja_obligacion('PROVEEDOR', 320.0, 'Juan Quispe'), $admin);
    $compraId = caja_compra($admin);
    caja_pagar(caja_obligacion('PERSONAL', 1500.0, 'David López'), $admin);

    $movimientos = MovimientoCajaModel::orderBy('id')->get();
    expect($movimientos)->toHaveCount(4)
        ->and($movimientos->map(fn ($m): array => [$m->tipo, $m->categoria, (float) $m->monto, $m->referencia_tipo])->all())->toBe([
            ['ENTRADA', 'VENTA', 10000.0, 'VENTA'],
            ['SALIDA', 'PROVEEDOR', 320.0, 'PAGO_PROVEEDOR'],
            ['SALIDA', 'INSUMOS', 450.0, 'COMPRA_INSUMOS'],
            ['SALIDA', 'TRABAJADOR', 1500.0, 'PAGO_TRABAJADOR'],
        ])
        ->and($movimientos[0]->referencia_id)->toBe($ventaId)
        ->and($movimientos[0]->registrado_por_user_id)->toBe($vendedor->id)
        ->and($movimientos[1]->referencia_id)->toBe(PagoModel::orderBy('id')->first()->id)
        ->and($movimientos[1]->registrado_por_user_id)->toBe($admin->id)
        ->and($movimientos[2]->referencia_id)->toBe($compraId)
        ->and($movimientos[2]->descripcion)->toBe('Boleta B001-058');

    $this->actingAs($admin)->get(route('admin.caja.index'))
        ->assertOk()
        ->assertSeeInOrder(['Saldo disponible', 'S/ 7,730.00', 'Ingresos de hoy', 'S/ 10,000.00', 'Salidas de hoy', 'S/ 2,270.00', 'Movimientos de hoy', '4'])
        ->assertSee('Últimos movimientos')
        ->assertSee('+ S/ 10,000.00')
        ->assertSee('- S/ 320.00')
        ->assertSee('Juan Quispe')
        ->assertSee('Distribuidora ABC')
        ->assertDontSee('Nuevo movimiento');
});

it('una venta en descuento de planilla y una compra sin total no mueven dinero de la caja', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    caja_compra($admin, null);
    $planilla = new VentaProducto(
        id: 1, productoId: 1, proveedorId: 1, compradorId: null, tipoCliente: 'PROVEEDOR', cantidad: 2.0,
        precioUnitario: 15.0, importeTotal: 30.0, formaPago: 'DESCUENTO_PLANILLA', periodoId: null,
        estado: 'REGISTRADA', fecha: new DateTimeImmutable, registradoPorUserId: $admin->id,
    );

    expect(app(RegistrarMovimientoCaja::class)->porVenta($planilla, 'Proveedor Test', 'Queso'))->toBeNull()
        ->and(MovimientoCajaModel::count())->toBe(0);
});

it('un pago repetido no duplica la salida y registrar el mismo evento dos veces deja un solo movimiento', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = caja_obligacion('PERSONAL', 1500.0, 'David López');
    caja_pagar($obligacion, $admin);

    expect(fn () => caja_pagar($obligacion, $admin))->toThrow(DomainException::class, 'ya fue registrado');

    $pago = app(PagoRealizadoRepository::class)->buscarPorObligacionId($obligacion->id);
    $entidad = app(ObligacionPagoRepository::class)->buscarPorId($obligacion->id);
    app(RegistrarMovimientoCaja::class)->porPago($entidad, $pago);

    expect(MovimientoCajaModel::count())->toBe(1)
        ->and((float) MovimientoCajaModel::sole()->monto)->toBe(1500.0);
});

it('es atómico: si la caja falla, el pago no queda registrado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = caja_obligacion('PROVEEDOR', 320.0, 'Juan Quispe');
    mock(MovimientoCajaRepository::class)->shouldReceive('guardarSiNoExiste')->andThrow(new RuntimeException('caja caída'));

    expect(fn () => caja_pagar($obligacion, $admin))->toThrow(RuntimeException::class);

    expect(PagoModel::count())->toBe(0)
        ->and($obligacion->fresh()->estado)->toBe('PENDIENTE');
});

it('filtra por tipo, categoría y búsqueda', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    caja_venta($admin);
    caja_pagar(caja_obligacion('PROVEEDOR', 320.0, 'Juan Quispe'), $admin);
    caja_compra($admin);
    caja_pagar(caja_obligacion('PERSONAL', 1500.0, 'David López'), $admin);

    $ver = fn (array $query) => $this->actingAs($admin)->get(route('admin.caja.index', $query))->assertOk();

    $ver(['tipo' => 'ENTRADA'])->assertSee('Distribuidora ABC')->assertDontSee('Juan Quispe')->assertDontSee('David López');
    $ver(['tipo' => 'SALIDA'])->assertDontSee('Distribuidora ABC')->assertSee('Juan Quispe')->assertSee('Boleta B001-058')->assertSee('David López');
    $ver(['categoria' => 'VENTA'])->assertSee('Distribuidora ABC')->assertDontSee('Juan Quispe');
    $ver(['categoria' => 'PROVEEDOR'])->assertSee('Juan Quispe')->assertDontSee('David López')->assertDontSee('Distribuidora ABC');
    $ver(['categoria' => 'TRABAJADOR'])->assertSee('David López')->assertDontSee('Juan Quispe');
    $ver(['categoria' => 'INSUMOS'])->assertSee('Boleta B001-058')->assertDontSee('David López');
    $ver(['q' => 'quispe'])->assertSee('Juan Quispe')->assertDontSee('David López');
    $ver([])->assertSee('Distribuidora ABC')->assertSee('Juan Quispe')->assertSee('Boleta B001-058')->assertSee('David López');
});

it('las tarjetas de hoy solo cuentan movimientos de hoy; el saldo cuenta todos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    caja_venta($admin, precio: 50.0, cantidad: 2.0);
    MovimientoCajaModel::create([
        'tipo' => 'SALIDA', 'categoria' => 'INSUMOS', 'monto' => 30, 'concepto' => 'Compra de insumos', 'referencia' => 'C-0099',
        'referencia_tipo' => 'COMPRA_INSUMOS', 'referencia_id' => 99, 'registrado_por_user_id' => $admin->id, 'fecha' => now()->subDay(),
    ]);

    $this->actingAs($admin)->get(route('admin.caja.index'))
        ->assertOk()
        ->assertSeeInOrder(['S/ 70.00', 'Ingresos de hoy', 'S/ 100.00', 'Salidas de hoy', 'S/ 0.00', 'Movimientos de hoy', '1']);
});

it('el panel de detalle muestra el origen, la referencia y quién lo registró', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    caja_pagar(caja_obligacion('PROVEEDOR', 320.0, 'Juan Quispe'), $admin);
    $movimiento = MovimientoCajaModel::sole();

    $this->actingAs($admin)->get(route('admin.caja.panel', $movimiento->id))
        ->assertOk()
        ->assertSeeInOrder(['Pago a proveedor', '- S/ 320.00', 'Salida', 'Proveedor', 'Juan Quispe', 'Semana', 'Liquidación SEM-', 'Registrado por', 'Administrador', 'Fecha']);
});

it('Caja es solo del administrador y aparece en Finanzas antes de Pagos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs(fixture_usuario('VENTAS'))->get(route('admin.caja.index'))->assertForbidden();

    $this->actingAs($admin)->get(route('admin.caja.index'))
        ->assertOk()
        ->assertSeeInOrder(['Finanzas', 'Caja', 'Pagos', 'Pagar a proveedores', 'Pagar a trabajadores']);
});
