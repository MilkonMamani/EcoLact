<?php

use App\Application\Produccion\BoletaIlegibleException;
use App\Application\Produccion\CalcularCompraInsumos;
use App\Application\Produccion\Contracts\BoletaCompraOcrService;
use App\Application\Produccion\GuardarPresentacionInsumo;
use App\Application\Produccion\ObtenerPanelInsumo;
use App\Application\Produccion\ProcesarBoletaCompra;
use App\Application\Produccion\RegistrarCompraInsumos;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Infrastructure\Persistence\Models\CompraInsumoDetalleModel;
use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Repositories\EloquentInsumoMovimientoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\mock;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Sal (g) con Saco 25 kg y Cuajo (ml) con Balde 20 L.
 *
 * @return array{sal: int, cuajo: int, saco: int, balde: int}
 */
function compra_escenario(int $userId, float $stockSal = 1430.0, float $stockCuajo = 743.0): array
{
    $sal = fixture_insumo('Sal', 'g', $stockSal, 0.0, $userId);
    $cuajo = fixture_insumo('Cuajo', 'ml', $stockCuajo, 0.0, $userId);
    $guardar = app(GuardarPresentacionInsumo::class);

    return [
        'sal' => $sal->id(),
        'cuajo' => $cuajo->id(),
        'saco' => $guardar->execute($sal->id(), 'Saco', 25, 'kg')->id(),
        'balde' => $guardar->execute($cuajo->id(), 'Balde', 20, 'L')->id(),
    ];
}

function compra_stock(int $insumoId): float
{
    return (float) InsumoModel::findOrFail($insumoId)->stock_actual;
}

function compra_boleta_lee(string $texto): void
{
    mock(BoletaCompraOcrService::class)->shouldReceive('leerLecturas')->once()->andReturn([$texto]);
}

it('la presentación convierte su contenido a la unidad base con UnidadMedida', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'g', 0.0, 0.0, $admin->id);
    $cuajo = fixture_insumo('Cuajo', 'ml', 0.0, 0.0, $admin->id);

    expect(app(GuardarPresentacionInsumo::class)->execute($sal->id(), 'Saco', 25, 'kg')->cantidadBase())->toBe(25000.0);
    expect(app(GuardarPresentacionInsumo::class)->execute($cuajo->id(), 'Balde', 20, 'L')->cantidadBase())->toBe(20000.0);
    $this->assertDatabaseHas('presentaciones_insumo', ['insumo_id' => $sal->id(), 'nombre' => 'Saco', 'unidad' => 'kg', 'cantidad_base' => 25000]);
});

it('rechaza una presentación con unidad incompatible, duplicada o para la leche', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'g', 0.0, 0.0, $admin->id);
    $guardar = app(GuardarPresentacionInsumo::class);
    $guardar->execute($sal->id(), 'Saco', 25, 'kg');

    expect(fn () => $guardar->execute($sal->id(), 'Balde', 20, 'L'))->toThrow(DomainException::class, 'no es compatible');
    expect(fn () => $guardar->execute($sal->id(), 'sacos', 25000, 'g'))->toThrow(DomainException::class, 'ya tiene la presentación');
    expect(fn () => $guardar->execute(fixture_leche()->id, 'Bidón', 20, 'L'))->toThrow(DomainException::class, 'La leche no tiene presentaciones');
    $this->assertDatabaseCount('presentaciones_insumo', 1);
});

it('calcula el total a ingresar y el stock resultante sin mover inventario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);

    $calculo = app(CalcularCompraInsumos::class)->execute([
        ['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2],
        ['insumo_id' => $e['cuajo'], 'presentacion_id' => $e['balde'], 'cantidad' => 1],
    ]);

    expect(array_column($calculo['lineas'], 'total', 'insumo'))->toEqual(['Sal' => 50000, 'Cuajo' => 20000]);
    expect(array_column($calculo['impacto'], 'stock_resultante', 'insumo'))->toEqual(['Sal' => 51430, 'Cuajo' => 20743]);
    expect(compra_stock($e['sal']))->toBe(1430.0);
    $this->assertDatabaseCount('compras_insumo', 0);
});

it('confirmar la compra crea compra, detalles y movimientos de entrada, y aumenta el stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);

    $compra = app(RegistrarCompraInsumos::class)->execute([
        ['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2],
        ['insumo_id' => $e['cuajo'], 'presentacion_id' => $e['balde'], 'cantidad' => 1],
    ], ['proveedor_comercial' => 'Distribuidora X', 'numero_comprobante' => 'b001-203', 'fecha' => '2026-10-26'], $admin->id);

    expect(compra_stock($e['sal']))->toBe(51430.0);
    expect(compra_stock($e['cuajo']))->toBe(20743.0);

    $this->assertDatabaseHas('compras_insumo', ['id' => $compra->id(), 'origen' => 'MANUAL', 'numero_comprobante' => 'B001-203', 'registrado_por_user_id' => $admin->id]);
    $this->assertDatabaseHas('compra_insumo_detalles', ['compra_insumo_id' => $compra->id(), 'insumo_id' => $e['sal'], 'presentacion_nombre' => 'Saco', 'cantidad_presentaciones' => 2, 'cantidad_base' => 50000, 'unidad_base' => 'g']);

    $movimiento = InsumoMovimientoModel::where('insumo_id', $e['sal'])->where('motivo', 'INGRESO_COMPRA')->sole();
    expect($movimiento->tipo)->toBe('ENTRADA');
    expect((float) $movimiento->cantidad)->toBe(50000.0);
    expect($movimiento->origen_tipo)->toBe(InsumoMovimiento::ORIGEN_COMPRA_INSUMO);
    expect($movimiento->origen_id)->toBe($compra->id());
    expect($movimiento->observacion)->toBe('Boleta B001-203 · 2 × Saco 25 kg');
    expect(CompraInsumoDetalleModel::where('insumo_id', $e['sal'])->value('insumo_movimiento_id'))->toBe($movimiento->id);
});

it('la leche no se puede comprar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);

    expect(fn () => app(RegistrarCompraInsumos::class)->execute([
        ['insumo_id' => fixture_leche()->id, 'presentacion_id' => $e['saco'], 'cantidad' => 2],
    ], [], $admin->id))->toThrow(DomainException::class, 'La leche no se compra');

    $this->assertDatabaseCount('compras_insumo', 0);
});

it('una presentación de otro insumo invalida la compra completa', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);

    expect(fn () => app(RegistrarCompraInsumos::class)->execute([
        ['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2],
        ['insumo_id' => $e['cuajo'], 'presentacion_id' => $e['saco'], 'cantidad' => 1],
    ], [], $admin->id))->toThrow(DomainException::class, 'Seleccione la presentación de Cuajo');

    expect(compra_stock($e['sal']))->toBe(1430.0);
    $this->assertDatabaseCount('compras_insumo', 0);
});

it('si falla un movimiento a mitad de la compra se deshace todo (rollback)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);

    // El segundo movimiento falla: ni la compra, ni el primer movimiento, ni el stock deben quedar.
    $real = app(EloquentInsumoMovimientoRepository::class);
    app()->instance(InsumoMovimientoRepository::class, new class($real) implements InsumoMovimientoRepository
    {
        private int $llamadas = 0;

        public function __construct(private EloquentInsumoMovimientoRepository $real) {}

        public function guardar(InsumoMovimiento $movimiento): InsumoMovimiento
        {
            if (++$this->llamadas === 2) {
                throw new RuntimeException('falla simulada');
            }

            return $this->real->guardar($movimiento);
        }

        public function listarPorInsumo(int $insumoId): array
        {
            return $this->real->listarPorInsumo($insumoId);
        }

        public function listarPorOrigen(string $origenTipo, int $origenId): array
        {
            return $this->real->listarPorOrigen($origenTipo, $origenId);
        }

        public function listar(): array
        {
            return $this->real->listar();
        }

        public function ultimoMovimientoPorInsumo(): array
        {
            return $this->real->ultimoMovimientoPorInsumo();
        }
    });

    expect(fn () => app(RegistrarCompraInsumos::class)->execute([
        ['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2],
        ['insumo_id' => $e['cuajo'], 'presentacion_id' => $e['balde'], 'cantidad' => 1],
    ], [], $admin->id))->toThrow(RuntimeException::class);

    expect(compra_stock($e['sal']))->toBe(1430.0);
    $this->assertDatabaseCount('compras_insumo', 0);
    $this->assertDatabaseCount('compra_insumo_detalles', 0);
    expect(InsumoMovimientoModel::where('motivo', 'INGRESO_COMPRA')->count())->toBe(0);
});

it('editar la presentación después no cambia una compra histórica', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);
    $compra = app(RegistrarCompraInsumos::class)->execute([['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2]], [], $admin->id);

    app(GuardarPresentacionInsumo::class)->execute($e['sal'], 'Saco', 50, 'kg', $e['saco']);

    $detalle = CompraInsumoDetalleModel::where('compra_insumo_id', $compra->id())->sole();
    expect((float) $detalle->presentacion_cantidad)->toBe(25.0);
    expect((float) $detalle->cantidad_base)->toBe(50000.0);
});

it('la boleta reconoce insumos y presentaciones y propone la compra sin tocar el stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);
    compra_boleta_lee("2 SACOS SAL\n1 BALDE CUAJO");

    $lectura = app(ProcesarBoletaCompra::class)->execute('boleta.jpg');

    expect($lectura['lineas'])->toHaveCount(2);
    expect($lectura['lineas'][0])->toMatchArray([
        'cantidad' => 2.0, 'presentacion_detectada' => 'Saco', 'insumo_id' => $e['sal'],
        'presentacion_id' => $e['saco'], 'requiere_confirmacion_presentacion' => false, 'estado' => ProcesarBoletaCompra::RECONOCIDA,
    ]);
    expect($lectura['lineas'][1])->toMatchArray(['insumo_id' => $e['cuajo'], 'presentacion_id' => $e['balde'], 'estado' => ProcesarBoletaCompra::RECONOCIDA]);
    expect(compra_stock($e['sal']))->toBe(1430.0);
    $this->assertDatabaseCount('compras_insumo', 0);
});

it('con dos sacos configurados la boleta NO elige: pide confirmar la presentación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);
    $sacoGrande = app(GuardarPresentacionInsumo::class)->execute($e['sal'], 'Saco', 50, 'kg')->id();
    compra_boleta_lee('2 SACOS SAL');

    $linea = app(ProcesarBoletaCompra::class)->execute('boleta.jpg')['lineas'][0];

    expect($linea['requiere_confirmacion_presentacion'])->toBeTrue();
    expect($linea['presentacion_id'])->toBeNull();
    expect($linea['estado'])->toBe(ProcesarBoletaCompra::PRESENTACION_AMBIGUA);
    expect($linea['opciones_presentacion'])->toEqualCanonicalizing([$e['saco'], $sacoGrande]);
});

it('la boleta no crea insumos desconocidos ni asume presentaciones que el insumo no tiene', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);
    compra_boleta_lee("2 BALDES CONSERVANTE X\n3 SACOS CUAJO");

    [$desconocido, $sinPresentacion] = app(ProcesarBoletaCompra::class)->execute('boleta.jpg')['lineas'];

    expect($desconocido)->toMatchArray(['insumo_id' => null, 'presentacion_id' => null, 'descripcion' => 'CONSERVANTE', 'estado' => ProcesarBoletaCompra::INSUMO_NO_RECONOCIDO]);
    expect($sinPresentacion)->toMatchArray(['insumo_id' => $e['cuajo'], 'presentacion_id' => null, 'estado' => ProcesarBoletaCompra::PRESENTACION_NO_CONFIGURADA]);
    $this->assertDatabaseMissing('insumos', ['nombre' => 'Conservante']);
});

it('sin presentación en la boleta y una sola configurada, se selecciona esa', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);
    compra_boleta_lee('1 CUAJO');

    $linea = app(ProcesarBoletaCompra::class)->execute('boleta.jpg')['lineas'][0];

    expect($linea['presentacion_id'])->toBe($e['balde']);
    expect($linea['estado'])->toBe(ProcesarBoletaCompra::RECONOCIDA);
});

it('la boleta nunca propone leche aunque aparezca en el texto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    compra_escenario($admin->id);
    compra_boleta_lee('20 LECHE');

    expect(app(ProcesarBoletaCompra::class)->execute('boleta.jpg')['lineas'])->toBe([]);
});

it('si el OCR falla se informa como boleta ilegible', function () {
    mock(BoletaCompraOcrService::class)->shouldReceive('leerLecturas')->andThrow(new BoletaIlegibleException('tesseract no encontrado'));

    expect(fn () => app(ProcesarBoletaCompra::class)->execute('boleta.jpg'))->toThrow(BoletaIlegibleException::class);
});

it('el panel muestra los movimientos de compra con su boleta y los de producción con su lote', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_escenario($admin->id);
    $compra = app(RegistrarCompraInsumos::class)->execute([['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2]], ['numero_comprobante' => 'B001-203'], $admin->id);

    $movimiento = app(ObtenerPanelInsumo::class)->execute($e['sal'])['movimientos'][0];

    expect($movimiento)->toMatchArray(['entrada' => true, 'cantidad' => 50000.0, 'motivo' => 'Compra', 'referencia' => 'Boleta B001-203', 'compra_id' => $compra->id()]);
    expect(CompraInsumoModel::count())->toBe(1);
});
