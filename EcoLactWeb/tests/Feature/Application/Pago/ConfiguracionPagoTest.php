<?php

use App\Application\Pago\ActualizarConfiguracionPago;
use App\Application\Pago\GenerarPagosPendientes;
use App\Application\Pago\ObtenerConfiguracionesPago;
use App\Application\Pago\RegistrarConfiguracionPago;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ReglaPagoCalidadModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

const ROLES_DE_PERSONAL = ['ACOPIADOR', 'CALIDAD', 'ENCARGADO_PLANTA', 'VENTAS', 'ADMINISTRADOR'];

function filas_de_personal(): array
{
    return collect(app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'))['personal'])
        ->keyBy('rolNombre')->all();
}

// --------------------------------------------------------------------------
// Roles remunerables (salen de la tabla roles)
// --------------------------------------------------------------------------

it('la tabla de personal muestra solo los roles remunerables de la tabla roles', function () {
    foreach ([...ROLES_DE_PERSONAL, 'PROVEEDOR'] as $rol) {
        fixture_rol($rol);
    }

    expect(array_keys(filas_de_personal()))->toEqualCanonicalizing(ROLES_DE_PERSONAL);
});

it('PROVEEDOR queda fuera del personal y no admite un sueldo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $proveedor = fixture_rol('PROVEEDOR');

    expect(array_keys(filas_de_personal()))->not->toContain('PROVEEDOR');

    expect(fn () => app(RegistrarConfiguracionPago::class)->execute(TipoBeneficiarioPago::PERSONAL, $proveedor->id, 1000.0, false, new DateTimeImmutable('2026-09-01'), null, $admin->id))
        ->toThrow(DomainException::class, 'no es remunerable');

    expect(ConfiguracionPagoModel::count())->toBe(0);
});

it('un rol nuevo marcado como remunerable aparece solo, sin tocar código', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_rol('ACOPIADOR');

    expect(array_keys(filas_de_personal()))->not->toContain('ALMACENERO');

    $almacenero = RoleModel::create(['nombre' => 'ALMACENERO', 'descripcion' => null, 'es_remunerable' => true]);
    $filas = filas_de_personal();

    expect($filas)->toHaveKey('ALMACENERO')
        ->and($filas['ALMACENERO']['estado'])->toBe('SIN_CONFIGURAR');

    app(RegistrarConfiguracionPago::class)->execute(TipoBeneficiarioPago::PERSONAL, $almacenero->id, 900.0, false, new DateTimeImmutable('2026-09-01'), null, $admin->id);

    expect(filas_de_personal()['ALMACENERO']['estado'])->toBe('ACTIVA');
});

it('un rol nuevo remunerable genera sueldos para sus usuarios activos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    RoleModel::create(['nombre' => 'SUPERVISOR', 'descripcion' => null, 'es_remunerable' => true]);
    $persona = pagos_personal('SUPERVISOR', 'Sonia');
    pagos_configurar('SUPERVISOR', 1500, '2026-09-01', $admin);

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    $obligacion = ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail();

    expect((float) $obligacion->valor_dia)->toBe(50.0)
        ->and($obligacion->beneficiario_nombre)->toBe($persona->username)
        ->and($obligacion->base_calculo['rol'])->toBe('SUPERVISOR');
});

it('un rol que deja de ser remunerable deja de listarse y de generar pagos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('VENTAS');
    pagos_configurar('VENTAS', 1000, '2026-09-01', $admin);
    RoleModel::where('nombre', 'VENTAS')->update(['es_remunerable' => false]);

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect(array_keys(filas_de_personal()))->not->toContain('VENTAS')
        ->and(ObligacionPagoModel::count())->toBe(0);
});

// --------------------------------------------------------------------------
// Proveedor: precio base y ajustes por calidad
// --------------------------------------------------------------------------

it('crea la configuración de proveedor por litros, semanal y sin guardar el precio en la configuración', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, aplicaCalidad: true, precio: 1.70);

    $configuracion = ConfiguracionPagoModel::firstOrFail();

    expect($configuracion->tipo_beneficiario)->toBe('PROVEEDOR')
        ->and($configuracion->rol_id)->toBeNull()
        ->and($configuracion->tipo_calculo)->toBe('POR_LITROS')
        ->and($configuracion->periodicidad)->toBe('SEMANAL')
        ->and($configuracion->monto_base)->toBeNull()
        ->and($configuracion->aplica_calidad)->toBeTrue()
        ->and($configuracion->creado_por_user_id)->toBe($admin->id);
});

it('el precio base configurado queda como precio de leche vigente desde la fecha de inicio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);

    $precio = PrecioLecheModel::firstOrFail();
    $datos = app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect((float) $precio->precio_litro)->toBe(1.7)
        ->and($precio->fecha_inicio->format('Y-m-d'))->toBe('2026-09-01')
        ->and($precio->fecha_fin)->toBeNull()
        ->and($precio->estado)->toBe('ACTIVO')
        ->and($datos['precioBaseVigente'])->toBe(1.7);
});

it('cambiar el precio base abre una nueva vigencia y conserva el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin, 1.70);
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);
    $configuracion = ConfiguracionPagoModel::firstOrFail();

    app(ActualizarConfiguracionPago::class)->execute($configuracion->id, null, true, new DateTimeImmutable('2026-10-01'), null, $admin->id, precioBaseLitro: 1.80);

    $precios = PrecioLecheModel::orderBy('fecha_inicio')->get();

    expect($precios)->toHaveCount(2)
        ->and((float) $precios[0]->precio_litro)->toBe(1.7)
        ->and($precios[0]->fecha_fin->format('Y-m-d'))->toBe('2026-09-30')
        ->and((float) $precios[1]->precio_litro)->toBe(1.8)
        ->and($precios[1]->fecha_inicio->format('Y-m-d'))->toBe('2026-10-01')
        ->and($precios[1]->fecha_fin)->toBeNull();

    $repo = app(PrecioLecheRepository::class);

    expect($repo->buscarVigente(new DateTimeImmutable('2026-09-15'))->precioLitro())->toBe(1.7)
        ->and($repo->buscarVigente(new DateTimeImmutable('2026-10-15'))->precioLitro())->toBe(1.8);
});

it('no se puede registrar un precio con vigencia anterior a un precio ya programado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin, 1.70);
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);
    fixture_precio_leche(1.90, new DateTimeImmutable('2026-12-01'), $admin->id);
    $configuracion = ConfiguracionPagoModel::firstOrFail();

    expect(fn () => app(ActualizarConfiguracionPago::class)->execute($configuracion->id, null, true, new DateTimeImmutable('2026-10-01'), null, $admin->id, precioBaseLitro: 1.80))
        ->toThrow(DomainException::class, 'vigencia desde el 01/12/2026');
});

it('un proveedor exige un precio base mayor a cero', function (?float $precio) {
    $admin = fixture_usuario('ADMINISTRADOR');

    expect(fn () => app(RegistrarConfiguracionPago::class)->execute(TipoBeneficiarioPago::PROVEEDOR, null, null, false, new DateTimeImmutable('2026-09-01'), null, $admin->id, $precio))
        ->toThrow(DomainException::class, 'precio base por litro');
})->with([null, 0.0, -1.0]);

it('los ajustes por calidad se guardan con vigencia y se leen de reglas_pago_calidad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70, ajustes: [
        'ACEPTADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '0'],
        'OBSERVADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '-0.10'],
    ]);

    $reglas = ReglaPagoCalidadModel::orderBy('resultado_calidad')->get()->keyBy('resultado_calidad');
    $datos = app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect($reglas->keys()->all())->toBe(['ACEPTADA', 'OBSERVADA', 'RECHAZADA'])
        ->and((float) $reglas['OBSERVADA']->valor_ajuste)->toBe(-0.1)
        ->and((float) $reglas['RECHAZADA']->valor_ajuste)->toBe(0.0)
        ->and($datos['reglasActivas'])->toBe(3)
        ->and($datos['ajustesVigentes']['OBSERVADA']->valorAjuste())->toBe(-0.1);
});

it('cambiar un ajuste por calidad abre una nueva vigencia y no reescribe la anterior', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin, 1.70);
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);
    $configuracion = ConfiguracionPagoModel::firstOrFail();

    app(ActualizarConfiguracionPago::class)->execute($configuracion->id, null, true, new DateTimeImmutable('2026-10-01'), null, $admin->id, precioBaseLitro: 1.70, ajustes: [
        'ACEPTADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '0'],
        'OBSERVADA' => ['tipo_ajuste' => 'PORCENTAJE', 'valor_ajuste' => '-5'],
    ]);

    $observadas = ReglaPagoCalidadModel::where('resultado_calidad', 'OBSERVADA')->orderBy('fecha_inicio')->get();

    expect($observadas)->toHaveCount(2)
        ->and((float) $observadas[0]->valor_ajuste)->toBe(-0.3)
        ->and($observadas[0]->fecha_fin->format('Y-m-d'))->toBe('2026-09-30')
        ->and($observadas[1]->tipo_ajuste)->toBe('PORCENTAJE')
        ->and($observadas[1]->fecha_fin)->toBeNull()
        ->and(ReglaPagoCalidadModel::where('resultado_calidad', 'ACEPTADA')->count())->toBe(1);
});

it('sin ajustes por calidad no se tocan las reglas existentes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin, 1.70);

    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, aplicaCalidad: false, precio: 1.70, ajustes: [
        'OBSERVADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '-9'],
    ]);

    expect(ReglaPagoCalidadModel::count())->toBe(3)
        ->and((float) ReglaPagoCalidadModel::where('resultado_calidad', 'OBSERVADA')->value('valor_ajuste'))->toBe(-0.3);
});

// --------------------------------------------------------------------------
// Personal: sueldo fijo mensual por rol
// --------------------------------------------------------------------------

it('crea una configuración mensual de sueldo fijo para cada rol del personal', function (string $rol) {
    $admin = fixture_usuario('ADMINISTRADOR');

    pagos_configurar($rol, 1234.50, '2026-10-01', $admin);

    $configuracion = ConfiguracionPagoModel::firstOrFail();

    expect($configuracion->tipo_beneficiario)->toBe('PERSONAL')
        ->and($configuracion->rol->nombre)->toBe($rol)
        ->and($configuracion->tipo_calculo)->toBe('SUELDO_FIJO')
        ->and($configuracion->periodicidad)->toBe('MENSUAL')
        ->and((float) $configuracion->monto_base)->toBe(1234.50)
        ->and($configuracion->aplica_calidad)->toBeFalse();
})->with(ROLES_DE_PERSONAL);

it('no permite dos configuraciones activas del mismo rol con vigencias que se cruzan', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);

    expect(fn () => pagos_configurar('ACOPIADOR', 1200, '2026-10-01', $admin))
        ->toThrow(DomainException::class, 'Ya existe una configuración de pago');

    expect(ConfiguracionPagoModel::count())->toBe(1);
});

it('sí permite configurar otro rol con las mismas fechas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);
    pagos_configurar('CALIDAD', 1100, '2026-09-01', $admin);

    expect(ConfiguracionPagoModel::count())->toBe(2);
});

it('rechaza un sueldo cero o negativo', function (float $sueldo) {
    $admin = fixture_usuario('ADMINISTRADOR');

    expect(fn () => pagos_configurar('VENTAS', $sueldo, '2026-09-01', $admin))
        ->toThrow(DomainException::class, 'mayor a cero');
})->with([0.0, -50.0]);

it('cambiar un sueldo con pagos ya generados abre una nueva vigencia y no toca el pasado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));
    $original = ConfiguracionPagoModel::firstOrFail();

    $nueva = app(ActualizarConfiguracionPago::class)->execute($original->id, 1100.0, false, new DateTimeImmutable('2026-10-01'), 'Sueldo vigente desde octubre.', $admin->id);

    $original->refresh();

    expect(ConfiguracionPagoModel::count())->toBe(2)
        ->and($original->fecha_fin->format('Y-m-d'))->toBe('2026-09-30')
        ->and((float) $original->monto_base)->toBe(1000.0)
        ->and($nueva->montoBase())->toBe(1100.0)
        ->and($nueva->fechaInicio()->format('Y-m-d'))->toBe('2026-10-01')
        ->and($nueva->fechaFin())->toBeNull()
        ->and((float) ObligacionPagoModel::firstOrFail()->valor_dia)->toBe(round(1000 / 30, 2));
});

it('rechaza la nueva vigencia si no es posterior a la de una configuración que ya generó pagos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));
    $original = ConfiguracionPagoModel::firstOrFail();

    expect(fn () => app(ActualizarConfiguracionPago::class)->execute($original->id, 1100.0, false, new DateTimeImmutable('2026-09-01'), null, $admin->id))
        ->toThrow(DomainException::class, 'ya generó pagos');
});

it('una configuración sin pagos generados se corrige en el mismo registro', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('ENCARGADO_PLANTA', 1300, '2026-10-01', $admin);
    $original = ConfiguracionPagoModel::firstOrFail();

    app(ActualizarConfiguracionPago::class)->execute($original->id, 1350.0, false, new DateTimeImmutable('2026-10-01'), null, $admin->id);

    expect(ConfiguracionPagoModel::count())->toBe(1)
        ->and((float) $original->refresh()->monto_base)->toBe(1350.0)
        ->and(ObligacionPagoModel::count())->toBe(0);
});

it('adelantar una vigencia programada hasta cruzarse con la anterior es rechazado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('VENTAS');
    pagos_configurar('VENTAS', 1000, '2026-09-01', $admin);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));
    $primera = ConfiguracionPagoModel::firstOrFail();
    $segunda = app(ActualizarConfiguracionPago::class)->execute($primera->id, 1100.0, false, new DateTimeImmutable('2026-11-01'), null, $admin->id);

    expect(fn () => app(ActualizarConfiguracionPago::class)->execute($segunda->id(), 1100.0, false, new DateTimeImmutable('2026-10-15'), null, $admin->id))
        ->toThrow(DomainException::class, 'se cruza');

    expect(ConfiguracionPagoModel::count())->toBe(2);
});

it('la pantalla muestra una fila por rol con su estado de configuración', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    foreach (ROLES_DE_PERSONAL as $rol) {
        fixture_rol($rol);
    }
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);
    pagos_configurar('CALIDAD', 1100, '2026-11-01', $admin);

    $datos = app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'));
    $porRol = filas_de_personal();

    expect($porRol['ACOPIADOR']['estado'])->toBe('ACTIVA')
        ->and($porRol['CALIDAD']['estado'])->toBe('PROGRAMADA')
        ->and($porRol['VENTAS']['estado'])->toBe('SIN_CONFIGURAR')
        ->and($datos['kpis'])->toBe(['activas' => 1, 'proveedores' => 0, 'personal' => 1]);
});
