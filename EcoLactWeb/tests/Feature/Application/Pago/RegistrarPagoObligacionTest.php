<?php

use App\Application\Pago\ComprobanteSubido;
use App\Application\Pago\GenerarPagosPendientes;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Domain\Pago\PagoRealizado;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Genera la ventana de personal y la fuerza a PENDIENTE (30/30 días) sin
 * pasar por jornadas reales: estos tests no vuelven a llamar a
 * GenerarPagosPendientes antes de pagar, así que no hay riesgo de que una
 * recalculación posterior la devuelva a EN_CURSO (ver pagos_forzar_pendiente
 * en PagosFixtures.php para el caso en que sí hace falta esa consistencia).
 */
function obligacion_de_personal(User $admin, string $rol = 'ACOPIADOR', float $sueldo = 1000.0): ObligacionPagoModel
{
    pagos_personal($rol, 'Julio');
    pagos_configurar($rol, $sueldo, '2026-09-01', $admin);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    $obligacion = ObligacionPagoModel::where('tipo_beneficiario', 'PERSONAL')->where('beneficiario_nombre', 'Julio Apellido')->firstOrFail();
    $obligacion->update([
        'dias_trabajados' => 30,
        'valor_dia' => round($sueldo / 30, 2),
        'acumulado' => $sueldo,
        'monto_calculado' => $sueldo,
        'estado' => 'PENDIENTE',
    ]);

    return $obligacion->fresh();
}

function pagar(ObligacionPagoModel $obligacion, User $admin, array $datos = []): PagoRealizado
{
    return app(RegistrarPagoObligacion::class)->execute(
        obligacionId: $obligacion->id,
        metodoPago: $datos['metodo'] ?? MetodoPago::EFECTIVO,
        numeroOperacion: $datos['operacion'] ?? null,
        fechaPago: new DateTimeImmutable($datos['fecha'] ?? '2026-09-19'),
        observacion: $datos['observacion'] ?? null,
        comprobante: $datos['comprobante'] ?? null,
        registradoPorUserId: $admin->id,
        montoDeclarado: $datos['monto'] ?? null,
        hoy: new DateTimeImmutable('2026-09-20'),
    );
}

it('registrar el pago crea el pago real y cambia la obligación a PAGADO', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);

    $pago = pagar($obligacion, $admin, ['observacion' => 'Entregado en oficina']);

    $registro = PagoModel::firstOrFail();

    expect($obligacion->fresh()->estado)->toBe('PAGADO')
        ->and($pago->obligacionPagoId())->toBe($obligacion->id)
        ->and((float) $registro->monto)->toBe(1000.0)
        ->and($registro->metodo_pago)->toBe('EFECTIVO')
        ->and($registro->fecha_pago->format('Y-m-d'))->toBe('2026-09-19')
        ->and($registro->observacion)->toBe('Entregado en oficina')
        ->and($registro->registrado_por_user_id)->toBe($admin->id);
});

it('no permite pagar dos veces la misma obligación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);
    pagar($obligacion, $admin);

    expect(fn () => pagar($obligacion, $admin))->toThrow(DomainException::class, 'ya fue registrado');

    expect(PagoModel::count())->toBe(1);
});

it('la base de datos impide dos pagos reales para la misma obligación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);
    pagar($obligacion, $admin);

    expect(fn () => PagoModel::create([
        'obligacion_pago_id' => $obligacion->id, 'fecha_pago' => '2026-09-19', 'monto' => 1000,
        'metodo_pago' => 'EFECTIVO', 'registrado_por_user_id' => $admin->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('no admite pagos parciales ni un monto distinto al calculado', function (float $monto) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);

    expect(fn () => pagar($obligacion, $admin, ['monto' => $monto]))->toThrow(DomainException::class, 'exactamente el monto calculado');

    expect($obligacion->fresh()->estado)->toBe('PENDIENTE')->and(PagoModel::count())->toBe(0);
})->with([500.0, 1000.01, 1500.0]);

it('acepta el monto declarado cuando coincide exactamente con el calculado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);

    pagar($obligacion, $admin, ['monto' => 1000.00]);

    expect($obligacion->fresh()->estado)->toBe('PAGADO');
});

it('exige número de operación en los métodos electrónicos', function (MetodoPago $metodo) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);

    expect(fn () => pagar($obligacion, $admin, ['metodo' => $metodo, 'operacion' => '  ']))
        ->toThrow(DomainException::class, 'número de operación');

    pagar($obligacion, $admin, ['metodo' => $metodo, 'operacion' => 'OP-123456']);

    expect(PagoModel::firstOrFail()->numero_operacion)->toBe('OP-123456');
})->with([MetodoPago::TRANSFERENCIA, MetodoPago::YAPE, MetodoPago::PLIN, MetodoPago::DEPOSITO]);

it('el número de operación es opcional en efectivo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);

    pagar($obligacion, $admin, ['metodo' => MetodoPago::EFECTIVO, 'operacion' => null]);

    expect(PagoModel::firstOrFail()->numero_operacion)->toBeNull();
});

it('no acepta una fecha de pago futura ni anterior al periodo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);

    expect(fn () => pagar($obligacion, $admin, ['fecha' => '2026-09-21']))->toThrow(DomainException::class, 'posterior a hoy');
    expect(fn () => pagar($obligacion, $admin, ['fecha' => '2026-08-31']))->toThrow(DomainException::class, 'anterior al inicio');
});

it('guarda el comprobante en Storage privado y solo su ruta en la base de datos', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);
    $archivo = UploadedFile::fake()->create('voucher.pdf', 120, 'application/pdf');

    pagar($obligacion, $admin, [
        'metodo' => MetodoPago::TRANSFERENCIA,
        'operacion' => 'OP-1',
        'comprobante' => new ComprobanteSubido($archivo->getRealPath(), 'pdf', 'voucher.pdf'),
    ]);

    $pago = PagoModel::firstOrFail();

    expect($pago->comprobante_path)->toStartWith('comprobantes-pago/')->toEndWith('.pdf')
        ->and($pago->comprobante_nombre)->toBe('voucher.pdf');

    Storage::disk('local')->assertExists($pago->comprobante_path);
});

it('si el pago falla no deja el comprobante huérfano en Storage', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $obligacion = obligacion_de_personal($admin);
    pagar($obligacion, $admin);
    $archivo = UploadedFile::fake()->create('otro.pdf', 50, 'application/pdf');

    expect(fn () => pagar($obligacion, $admin, [
        'comprobante' => new ComprobanteSubido($archivo->getRealPath(), 'pdf', 'otro.pdf'),
    ]))->toThrow(DomainException::class);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('registrar un pago inexistente falla sin efectos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    expect(fn () => app(RegistrarPagoObligacion::class)->execute(
        999, MetodoPago::EFECTIVO, null, new DateTimeImmutable('2026-09-19'), null, null, $admin->id, null, new DateTimeImmutable('2026-09-20'),
    ))->toThrow(DomainException::class, 'no existe');
});

it('pagar a un proveedor deja su pago en PAGADO con el detalle de sus entregas intacto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin);
    $proveedor = fixture_proveedor(fixture_zona());
    pagos_entrega($proveedor, 100.0, '2026-09-10', 'ACEPTADA');
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));
    $obligacion = ObligacionPagoModel::where('tipo_beneficiario', 'PROVEEDOR')->firstOrFail();

    $pago = pagar($obligacion, $admin, ['metodo' => MetodoPago::YAPE, 'operacion' => 'YAPE-9']);

    $obligacion->refresh();

    expect($obligacion->estado)->toBe('PAGADO')
        ->and($obligacion->clave_abierta)->toBeNull()
        ->and($obligacion->detalles)->toHaveCount(1)
        ->and((float) $obligacion->monto_calculado)->toBe(210.0)
        ->and($pago->pagoProveedorId())->toBeNull()
        ->and((float) PagoModel::firstOrFail()->monto)->toBe(210.0)
        ->and(PagoModel::firstOrFail()->metodo_pago)->toBe('YAPE');
});

it('un pago realizado no tiene ruta ni operación de edición', function () {
    expect(Route::has('admin.pagos.update'))->toBeFalse()
        ->and(Route::has('admin.pagos.store'))->toBeFalse()
        ->and(Route::has('admin.pagos.destroy'))->toBeFalse();

    $rutas = collect(Route::getRoutes()->getRoutes())->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'admin/pagos'));

    expect($rutas->flatMap->methods()->unique()->values()->all())->toEqualCanonicalizing(['GET', 'HEAD', 'POST']);
});
