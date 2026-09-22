<?php

use App\Application\Acopio\RegistrarEntrega;
use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarProduccion;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\NotificacionModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RecetaDetalleModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Escenario completo con los casos de uso reales: entrega de 20 L, Leche, Cuajo, Sal,
 * receta, producto y un lote producido (que consume 10 L de leche).
 *
 * @return array{admin: User}
 */
function escenarioDePrueba(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        acopiadorUserId: $acopiador->user_id,
        litros: 20.0,
    );

    $cuajo = fixture_insumo('Cuajo', 'ml', 800.0, 100.0, $admin->id);
    $sal = fixture_insumo('Sal', 'kg', 50.0, 10.0, $admin->id);
    $producto = fixture_producto('Queso paria', 'kg');
    fixture_receta($producto->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 5.0, 'unidad' => 'ml'],
        ['insumo_id' => $sal->id(), 'cantidad' => 0.1, 'unidad' => 'kg'],
    ], $admin->id);

    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 1.0]], $admin->id);
    $detalle = ProduccionDetalleModel::where('lote_produccion_id', $lote->id())->firstOrFail();
    app(FinalizarProduccion::class)->execute($lote->id(), [$detalle->id => 1.0], $admin->id);

    return ['admin' => $admin];
}

function limpiarDatos(array $opciones = []): PendingCommand
{
    return test()->artisan('ecolact:limpiar-datos-produccion', $opciones);
}

it('borra recetas, producción, productos e insumos salvo Leche y conserva el acopio y los 20 L', function () {
    escenarioDePrueba();

    // Antes: la producción consumió 10 L, así que el stock es 10 L y hay datos de prueba.
    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(10.0);
    expect(InsumoModel::count())->toBe(3);
    expect(RecetaModel::count())->toBe(1);
    expect(LoteProduccionModel::count())->toBe(1);
    expect(ProductoModel::count())->toBe(1);
    expect(ProductoMovimientoModel::count())->toBeGreaterThan(0);

    $usuarios = User::count();
    $notificaciones = NotificacionModel::count();

    limpiarDatos(['--force' => true])->assertSuccessful();

    // Se conserva el acopio.
    expect(ProveedorModel::count())->toBe(1);
    expect(AcopiadorModel::count())->toBe(1);
    expect(EntregaModel::count())->toBe(1);
    expect((float) EntregaModel::sum('litros'))->toBe(20.0);
    expect(User::count())->toBe($usuarios);
    expect(NotificacionModel::count())->toBe($notificaciones);

    // Leche sigue siendo el insumo del sistema y sus 20 L siguen representados.
    $leche = InsumoModel::where('codigo', 'LECHE')->firstOrFail();
    expect($leche->tipo)->toBe('MATERIA_PRIMA');
    expect($leche->unidad_base)->toBe('L');
    expect((float) $leche->stock_actual)->toBe(20.0);
    expect(InsumoMovimientoModel::where('insumo_id', $leche->id)->count())->toBe(1);
    expect(InsumoMovimientoModel::where('insumo_id', $leche->id)->value('motivo'))->toBe('INGRESO_ACOPIO');

    // Se elimina todo el flujo de prueba.
    expect(InsumoModel::count())->toBe(1);
    expect(InsumoMovimientoModel::where('insumo_id', '!=', $leche->id)->count())->toBe(0);
    expect(RecetaModel::count())->toBe(0);
    expect(RecetaDetalleModel::count())->toBe(0);
    expect(LoteProduccionModel::count())->toBe(0);
    expect(ProduccionDetalleModel::count())->toBe(0);
    expect(ProductoModel::count())->toBe(0);
    expect(ProductoMovimientoModel::count())->toBe(0);
});

it('no borra nada si el usuario no confirma', function () {
    escenarioDePrueba();

    limpiarDatos()
        ->expectsConfirmation(
            'ATENCIÓN: se eliminarán recetas, producciones, productos terminados e insumos excepto Leche. '
            .'Proveedores, acopiadores y entregas serán conservados. ¿Deseas continuar?',
            'no',
        )
        ->assertSuccessful();

    expect(RecetaModel::count())->toBe(1);
    expect(LoteProduccionModel::count())->toBe(1);
    expect(ProductoModel::count())->toBe(1);
    expect(InsumoModel::count())->toBe(3);
    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(10.0);
});

it('--dry-run solo muestra el resumen y no modifica nada', function () {
    escenarioDePrueba();

    limpiarDatos(['--dry-run' => true])->assertSuccessful();

    expect(RecetaModel::count())->toBe(1);
    expect(InsumoModel::count())->toBe(3);
});

it('se niega a ejecutarse en producción', function () {
    escenarioDePrueba();
    app()->detectEnvironment(fn (): string => 'production');

    limpiarDatos(['--force' => true])->assertFailed();

    expect(RecetaModel::count())->toBe(1);
    expect(ProductoModel::count())->toBe(1);
    expect(InsumoModel::count())->toBe(3);
});

it('aborta sin tocar nada si existen ventas de productos (datos ajenos al flujo)', function () {
    ['admin' => $admin] = escenarioDePrueba();
    VentaProductoModel::create([
        'producto_id' => ProductoModel::firstOrFail()->id,
        'tipo_cliente' => 'EXTERNO',
        'cantidad' => 1,
        'precio_unitario' => 10,
        'importe_total' => 10,
        'forma_pago' => 'EFECTIVO',
        'estado' => 'REGISTRADA',
        'fecha' => now(),
        'registrado_por_user_id' => $admin->id,
    ]);

    limpiarDatos(['--force' => true])->assertFailed();

    expect(VentaProductoModel::count())->toBe(1);
    expect(ProductoModel::count())->toBe(1);
    expect(RecetaModel::count())->toBe(1);
    expect(InsumoModel::count())->toBe(3);
});

it('no borra nada si falta el insumo Leche', function () {
    escenarioDePrueba();
    InsumoModel::where('codigo', 'LECHE')->update(['codigo' => 'OTRO']);

    limpiarDatos(['--force' => true])->assertFailed();

    expect(RecetaModel::count())->toBe(1);
    expect(LoteProduccionModel::count())->toBe(1);
    expect(ProductoModel::count())->toBe(1);
});

it('no inventa stock: una entrega válida sin ingreso solo se reconstruye con --reconstruir-leche', function () {
    escenarioDePrueba();
    $zona = fixture_zona();
    // Entrega histórica creada sin pasar por el caso de uso: no tiene movimiento de leche.
    fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 25.0);

    limpiarDatos(['--force' => true])->assertSuccessful();
    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(20.0);
    expect(EntregaModel::count())->toBe(2);

    limpiarDatos(['--force' => true, '--reconstruir-leche' => true])->assertSuccessful();
    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(45.0);
    expect(EntregaModel::count())->toBe(2);
});
