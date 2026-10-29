<?php

use App\Application\Produccion\ActualizarInsumo;
use App\Application\Produccion\CalcularCompraInsumos;
use App\Application\Produccion\CalcularRequerimientosProduccion;
use App\Application\Produccion\GuardarPresentacionInsumo;
use App\Application\Produccion\IniciarProduccion;
use App\Application\Produccion\ObtenerPanelInsumo;
use App\Application\Produccion\RegistrarCompraInsumos;
use App\Domain\Produccion\UnidadMedida;
use App\Infrastructure\Persistence\Models\AuditoriaModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use App\Infrastructure\Persistence\Models\PagoCompraModel;
use App\Infrastructure\Persistence\Models\RecetaDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Sal en kg con Saco 25 kg y Cuajo en ml con Balde 20 L: el caso real de la planta.
 *
 * @return array{sal: int, cuajo: int, saco: int, balde: int}
 */
function unidades_escenario(int $userId): array
{
    $sal = fixture_insumo('Sal', 'kg', 0.0, 10.0, $userId);
    $cuajo = fixture_insumo('Cuajo', 'ml', 0.0, 10.0, $userId);
    $guardar = app(GuardarPresentacionInsumo::class);

    return [
        'sal' => $sal->id(),
        'cuajo' => $cuajo->id(),
        'saco' => $guardar->execute($sal->id(), 'Saco', 25, 'kg')->id(),
        'balde' => $guardar->execute($cuajo->id(), 'Balde', 20, 'L')->id(),
    ];
}

function unidades_stock(int $insumoId): float
{
    return (float) InsumoModel::findOrFail($insumoId)->stock_actual;
}

it('convierte dentro de la misma magnitud y rechaza mezclar masa con volumen', function () {
    expect(UnidadMedida::convertir(750, 'g', 'kg'))->toBe(0.75);
    expect(UnidadMedida::convertir(20, 'L', 'ml'))->toBe(20000.0);
    expect(fn () => UnidadMedida::convertir(1, 'kg', 'ml'))->toThrow(InvalidArgumentException::class, 'son magnitudes distintas');
    expect(fn () => UnidadMedida::convertir(1, 'L', 'g'))->toThrow(InvalidArgumentException::class, 'son magnitudes distintas');

    expect(array_keys(UnidadMedida::familias()))->toBe(['MASA', 'VOLUMEN', 'CONTEO']);
    expect(array_keys(UnidadMedida::familias()['MASA']['unidades']))->toBe(['kg', 'g']);
    expect(UnidadMedida::simbolo('unidad'))->toBe('und');
    expect(UnidadMedida::descripcionControl('kg'))->toBe('Controlado por peso');
});

it('Sal se controla en kg, Cuajo en ml y Leche en L', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = unidades_escenario($admin->id);

    expect(InsumoModel::find($e['sal'])->unidad_base)->toBe('kg');
    expect(InsumoModel::find($e['cuajo'])->unidad_base)->toBe('ml');
    expect(fixture_leche()->unidad_base)->toBe('L');
});

it('comprar 2 sacos de Sal y 1 balde de Cuajo convierte a la unidad de inventario, mueve stock una sola vez y registra la salida de caja', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = unidades_escenario($admin->id);
    $lineas = [
        ['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2, 'precio_unitario' => 40],
        ['insumo_id' => $e['cuajo'], 'presentacion_id' => $e['balde'], 'cantidad' => 1, 'precio_unitario' => 120],
    ];

    // Vista previa: calcula sin tocar el stock.
    $calculo = app(CalcularCompraInsumos::class)->execute($lineas);
    expect(array_column($calculo['lineas'], 'total'))->toBe([50.0, 20000.0]);
    expect(unidades_stock($e['sal']))->toBe(0.0);
    expect(InsumoMovimientoModel::count())->toBe(0);

    $compra = app(RegistrarCompraInsumos::class)->execute($lineas, ['numero_comprobante' => 'B001-1'], $admin->id);

    expect(unidades_stock($e['sal']))->toBe(50.0);
    expect(unidades_stock($e['cuajo']))->toBe(20000.0);
    expect(InsumoMovimientoModel::where('origen_tipo', 'COMPRA_INSUMO')->where('origen_id', $compra->id())->count())->toBe(2);
    // Una salida por el pago de la compra (PAGO_COMPRA), enlazada desde pagos_compra.
    expect(MovimientoCajaModel::where('referencia_tipo', 'PAGO_COMPRA')->whereKey(PagoCompraModel::where('compra_insumo_id', $compra->id())->sole()->movimiento_caja_id)->sole()->monto)->toEqual(200);
});

it('una receta en gramos descuenta su equivalente en kg: 750 g de Sal dejan 49.25 kg', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = unidades_escenario($admin->id);
    fixture_stock_leche(100.0, $admin->id);
    app(RegistrarCompraInsumos::class)->execute([['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2]], [], $admin->id);
    app(RegistrarCompraInsumos::class)->execute([['insumo_id' => $e['cuajo'], 'presentacion_id' => $e['balde'], 'cantidad' => 1]], [], $admin->id);
    $queso = fixture_producto('Queso fresco');
    fixture_receta($queso->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $e['sal'], 'cantidad' => 750.0, 'unidad' => 'g'],
        ['insumo_id' => $e['cuajo'], 'cantidad' => 0.05, 'unidad' => 'L'],
    ], $admin->id);

    app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 1]], $admin->id);

    expect(unidades_stock($e['sal']))->toBe(49.25);
    expect(unidades_stock($e['cuajo']))->toBe(19950.0);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(90.0);
});

it('una receta guardada con Sal en ml se señala como inconsistente en lugar de convertir', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = unidades_escenario($admin->id);
    $queso = fixture_producto('Queso fresco');
    $receta = fixture_receta($queso->id, [['insumo_id' => $e['sal'], 'cantidad' => 750.0, 'unidad' => 'g']], $admin->id);
    // Dato heredado: la receta quedó en ml (no se puede crear así por la aplicación).
    RecetaDetalleModel::where('receta_id', $receta->id())->update(['unidad' => 'ml']);

    expect(fn () => app(CalcularRequerimientosProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 1]]))
        ->toThrow(DomainException::class, 'La receta de Queso fresco indica Sal en ml, pero Sal se controla en kg. Corrige la receta antes de producir.');
});

it('un insumo sin historial puede corregir su unidad base y el cambio queda auditado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'ml', 0.0, 10.0, $admin->id);

    expect(app(ObtenerPanelInsumo::class)->execute($sal->id())['unidad_bloqueos'])->toBe([]);

    app(ActualizarInsumo::class)->execute($sal->id(), 'Sal', null, 10.0, 'ACTIVO', unidadBase: 'kg', userId: $admin->id);

    expect(InsumoModel::find($sal->id())->unidad_base)->toBe('kg');
    expect((float) InsumoModel::find($sal->id())->stock_minimo)->toBe(10.0);
    $auditoria = AuditoriaModel::where('accion', ActualizarInsumo::ACCION_CAMBIO_UNIDAD)->sole();
    expect($auditoria->registro_id)->toBe($sal->id());
    expect($auditoria->user_id)->toBe($admin->id);
    expect($auditoria->datos_anteriores['unidad_base'])->toBe('ml');
    expect($auditoria->datos_nuevos['unidad_base'])->toBe('kg');
});

it('con presentaciones, movimientos o siendo la leche, la unidad base no se cambia', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = unidades_escenario($admin->id);
    $azucar = fixture_insumo('Azúcar', 'g', 500.0, 0.0, $admin->id);
    fixture_stock_leche(10.0, $admin->id);

    expect(fn () => app(ActualizarInsumo::class)->execute($e['cuajo'], 'Cuajo', null, 10.0, 'ACTIVO', unidadBase: 'g'))
        ->toThrow(DomainException::class, 'No se puede cambiar la unidad base de Cuajo porque presentaciones de compra.');
    expect(fn () => app(ActualizarInsumo::class)->execute($azucar->id(), 'Azúcar', null, 0.0, 'ACTIVO', unidadBase: 'kg'))
        ->toThrow(DomainException::class, 'tiene stock, movimientos de stock');
    expect(fn () => app(ActualizarInsumo::class)->execute(fixture_leche()->id, 'Leche', null, 0.0, 'ACTIVO', unidadBase: 'ml'))
        ->toThrow(DomainException::class, 'es un insumo del sistema');

    expect(InsumoModel::find($e['cuajo'])->unidad_base)->toBe('ml');
    expect(InsumoModel::find($azucar->id())->unidad_base)->toBe('g');
    expect(fixture_leche()->fresh()->unidad_base)->toBe('L');
    expect(AuditoriaModel::count())->toBe(0);
});

it('la migración pasa a kg la Sal en ml sin historial y deja intactos Leche, Cuajo y una Sal con movimientos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $migracion = require database_path('migrations/2026_10_29_025205_corregir_unidad_base_de_sal_a_kilogramos.php');
    $sal = fixture_insumo('Sal', 'ml', 0.0, 10.0, $admin->id);
    $cuajo = fixture_insumo('Cuajo', 'ml', 0.0, 10.0, $admin->id);

    $migracion->up();

    expect(InsumoModel::find($sal->id())->unidad_base)->toBe('kg');
    expect((float) InsumoModel::find($sal->id())->stock_minimo)->toBe(10.0);
    expect(InsumoModel::find($cuajo->id())->unidad_base)->toBe('ml');
    expect(fixture_leche()->unidad_base)->toBe('L');
    expect(AuditoriaModel::where('registro_id', $sal->id())->count())->toBe(1);

    // Reversible mientras siga sin historial.
    $migracion->down();
    expect(InsumoModel::find($sal->id())->unidad_base)->toBe('ml');

    // Con un movimiento en ml no se reinterpreta nada.
    DB::table('insumo_movimientos')->insert([
        'insumo_id' => $sal->id(), 'tipo' => 'ENTRADA', 'motivo' => 'AJUSTE_ENTRADA', 'cantidad' => 5,
        'fecha' => now(), 'registrado_por_user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $migracion->up();
    expect(InsumoModel::find($sal->id())->unidad_base)->toBe('ml');
});
