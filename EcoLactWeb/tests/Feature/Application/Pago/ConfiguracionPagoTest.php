<?php

use App\Application\Pago\ActualizarConfiguracionPago;
use App\Application\Pago\Contracts\SueldosTrabajadoresQuery;
use App\Application\Pago\GenerarPagosPendientes;
use App\Application\Pago\ObtenerConfiguracionesPago;
use App\Application\Pago\RegistrarConfiguracionPago;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ReglaPagoCalidadModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array<string, array<string, mixed>> filas de la pestaña Trabajadores, por nombre
 */
function filas_de_trabajadores(string $hoy = '2026-09-20'): array
{
    return collect(app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable($hoy))['trabajadores'])
        ->keyBy('nombre')->all();
}

function configurar_sueldo(int $userId, float $sueldo, string $desde, int $adminId): void
{
    app(RegistrarConfiguracionPago::class)->execute(TipoBeneficiarioPago::PERSONAL, $userId, $sueldo, false, new DateTimeImmutable($desde), null, $adminId);
}

// --------------------------------------------------------------------------
// Trabajadores: salen de Trabajadores (fichas reales), no de los roles
// --------------------------------------------------------------------------

it('lista los trabajadores registrados con su rol real; uno nuevo aparece sin configurar', function () {
    pagos_personal('ACOPIADOR', 'Juan');
    pagos_personal('CALIDAD', 'David');
    pagos_personal('VENTAS', 'Ana');

    $filas = filas_de_trabajadores();

    expect(array_keys($filas))->toBe(['Ana Apellido', 'David Apellido', 'Juan Apellido'])
        ->and($filas['Juan Apellido']['rol'])->toBe('Acopiador')
        ->and($filas['David Apellido']['rol'])->toBe('Analista de calidad')
        ->and($filas['Ana Apellido']['rol'])->toBe('Personal de ventas')
        ->and($filas['Ana Apellido']['estado'])->toBe('SIN_CONFIGURAR')
        ->and($filas['Ana Apellido']['sueldo'])->toBeNull();
});

it('un usuario con rol de ficha pero sin ficha real no aparece; el administrador aparece con su rol porque cobra sueldo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_usuario('CALIDAD');
    pagos_personal('CALIDAD', 'David');
    fixture_rol('PROVEEDOR');
    fixture_usuario('PROVEEDOR');

    $filas = filas_de_trabajadores();

    expect(array_keys($filas))->toEqualCanonicalizing(['David Apellido', $admin->username])
        ->and($filas[$admin->username]['rol'])->toBe('Administrador');
});

it('un trabajador inactivo solo aparece si tuvo sueldo, y como Inactivo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('VENTAS', 'Sin historial', estado: 'INACTIVO');
    $conHistorial = pagos_personal('ACOPIADOR', 'Con historial');
    configurar_sueldo($conHistorial->id, 1500, '2026-09-01', $admin->id);
    $conHistorial->update(['estado' => 'INACTIVO']);

    $filas = filas_de_trabajadores();

    expect($filas)->not->toHaveKey('Sin historial Apellido')
        ->and($filas['Con historial Apellido']['estado'])->toBe('INACTIVO');
});

it('configurar el sueldo guarda el sueldo del trabajador con su rol real y calcula el valor por día', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');

    configurar_sueldo($juan->id, 1500, '2026-09-01', $admin->id);

    $configuracion = ConfiguracionPagoModel::sole();
    $fila = filas_de_trabajadores()['Juan Apellido'];

    expect($configuracion->user_id)->toBe($juan->id)
        ->and($configuracion->rol->nombre)->toBe('ACOPIADOR')
        ->and($configuracion->tipo_calculo)->toBe('SUELDO_FIJO')
        ->and($configuracion->periodicidad)->toBe('MENSUAL')
        ->and($configuracion->aplica_calidad)->toBeFalse()
        ->and($fila['estado'])->toBe('ACTIVO')
        ->and($fila['sueldo'])->toBe(1500.0)
        ->and($fila['valorDia'])->toBe(50.0)
        ->and($fila['vigenteDesde'])->toBe('2026-09-01');
});

it('el valor por día es el sueldo mensual entre 30, redondeado a céntimos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $david = pagos_personal('CALIDAD', 'David');

    configurar_sueldo($david->id, 1700, '2026-09-01', $admin->id);

    expect(filas_de_trabajadores()['David Apellido']['valorDia'])->toBe(56.67);
});

it('solo se configura el sueldo de un trabajador activo con rol remunerable', function (string $caso) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $usuario = match ($caso) {
        'ficha inactiva' => pagos_personal('CALIDAD', 'Ana', estado: 'INACTIVO'),
        'usuario sin ficha real' => fixture_usuario('ACOPIADOR'),
        'proveedor' => fixture_usuario('PROVEEDOR'),
    };

    expect(fn () => configurar_sueldo($usuario->id, 1000, '2026-09-01', $admin->id))
        ->toThrow(DomainException::class, 'trabajador activo');

    expect(ConfiguracionPagoModel::count())->toBe(0);
})->with(['ficha inactiva', 'usuario sin ficha real', 'proveedor']);

it('un rol que deja de ser remunerable no genera pagos aunque su trabajador tenga sueldo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('VENTAS');
    pagos_configurar('VENTAS', 1000, '2026-09-01', $admin);
    RoleModel::where('nombre', 'VENTAS')->update(['es_remunerable' => false]);

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect(ObligacionPagoModel::count())->toBe(0);
});

it('no permite dos sueldos activos del mismo trabajador con vigencias que se cruzan', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    configurar_sueldo($juan->id, 1000, '2026-09-01', $admin->id);

    expect(fn () => configurar_sueldo($juan->id, 1200, '2026-10-01', $admin->id))
        ->toThrow(DomainException::class, 'Ya existe una configuración de pago para Juan Apellido');

    expect(ConfiguracionPagoModel::count())->toBe(1);
});

it('dos trabajadores del mismo rol pueden tener sueldos distintos con las mismas fechas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    $pedro = pagos_personal('ACOPIADOR', 'Pedro');

    configurar_sueldo($juan->id, 1500, '2026-09-01', $admin->id);
    configurar_sueldo($pedro->id, 1400, '2026-09-01', $admin->id);

    $filas = filas_de_trabajadores();

    expect($filas['Juan Apellido']['sueldo'])->toBe(1500.0)
        ->and($filas['Pedro Apellido']['sueldo'])->toBe(1400.0);
});

it('rechaza un sueldo cero o negativo', function (float $sueldo) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ana = pagos_personal('VENTAS', 'Ana');

    expect(fn () => configurar_sueldo($ana->id, $sueldo, '2026-09-01', $admin->id))
        ->toThrow(DomainException::class, 'mayor a cero');
})->with([0.0, -50.0]);

// --------------------------------------------------------------------------
// Vigencias: cambiar un sueldo nunca reescribe el pasado
// --------------------------------------------------------------------------

it('cambiar un sueldo con una fecha posterior abre una nueva vigencia y deja el anterior en el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    configurar_sueldo($juan->id, 1500, '2026-09-01', $admin->id);
    $original = ConfiguracionPagoModel::sole();

    app(ActualizarConfiguracionPago::class)->execute($original->id, 1800.0, false, new DateTimeImmutable('2026-10-25'), 'Aumento.', $admin->id);

    $detalle = app(SueldosTrabajadoresQuery::class)->detalle($juan->id, new DateTimeImmutable('2026-10-26'));

    expect(ConfiguracionPagoModel::count())->toBe(2)
        ->and((float) $original->refresh()->monto_base)->toBe(1500.0)
        ->and($original->fecha_fin->format('Y-m-d'))->toBe('2026-10-24')
        ->and($detalle['sueldo'])->toBe(1800.0)
        ->and($detalle['vigenteDesde'])->toBe('2026-10-25')
        ->and($detalle['historial'])->toBe([['sueldo' => 1500.0, 'desde' => '2026-09-01', 'hasta' => '2026-10-24']]);
});

it('cambiar un sueldo con pagos ya generados no toca el pago del periodo anterior', function () {
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
        ->and($nueva->userId())->toBe($original->user_id)
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

it('con la misma fecha y sin pagos generados solo se corrige el mismo registro', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ENCARGADO_PLANTA');
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

it('con varias vigencias muestra la vigente hoy, no la última registrada, y anuncia la próxima', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    configurar_sueldo($juan->id, 1500, '2026-09-01', $admin->id);
    app(ActualizarConfiguracionPago::class)->execute(ConfiguracionPagoModel::sole()->id, 1800.0, false, new DateTimeImmutable('2026-11-01'), null, $admin->id);

    $detalle = app(SueldosTrabajadoresQuery::class)->detalle($juan->id, new DateTimeImmutable('2026-09-20'));

    expect($detalle['estado'])->toBe('ACTIVO')
        ->and($detalle['sueldo'])->toBe(1500.0)
        ->and($detalle['vigenteDesde'])->toBe('2026-09-01')
        ->and($detalle['proxima'])->toBe(['sueldo' => 1800.0, 'desde' => '2026-11-01', 'hasta' => null]);
});

it('la configuración general de proveedores no aparece como trabajador ni se ve afectada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    configurar_sueldo($juan->id, 1500, '2026-09-01', $admin->id);

    $datos = app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'));
    $proveedor = ConfiguracionPagoModel::where('tipo_beneficiario', 'PROVEEDOR')->sole();

    expect(collect($datos['trabajadores'])->pluck('configuracionId')->filter()->values()->all())->toBe([ConfiguracionPagoModel::where('user_id', $juan->id)->value('id')])
        ->and($proveedor->user_id)->toBeNull()
        ->and($proveedor->rol_id)->toBeNull()
        ->and($datos['proveedor']['configuracion']->id())->toBe($proveedor->id)
        ->and($datos['proveedor']['precioBase'])->toBe(1.7);
});

it('un sueldo con vigencia futura se muestra como programado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $david = pagos_personal('CALIDAD', 'David');
    configurar_sueldo($david->id, 1100, '2026-11-01', $admin->id);

    expect(filas_de_trabajadores()['David Apellido']['estado'])->toBe('PROGRAMADO');
});

// --------------------------------------------------------------------------
// Migración: el sueldo por rol se copia a cada trabajador de ese rol
// --------------------------------------------------------------------------

it('la migración copia el sueldo por rol vigente a cada trabajador real del rol y conserva la fila por rol', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    pagos_personal('CALIDAD', 'David');
    $sinFicha = fixture_usuario('ACOPIADOR');
    $migracion = require database_path('migrations/2026_10_25_184254_add_user_id_to_configuraciones_pago_table.php');
    $migracion->down();
    $porRol = DB::table('configuraciones_pago')->insertGetId([
        'tipo_beneficiario' => 'PERSONAL', 'rol_id' => fixture_rol('ACOPIADOR')->id, 'tipo_calculo' => 'SUELDO_FIJO',
        'monto_base' => 1500, 'periodicidad' => 'MENSUAL', 'aplica_calidad' => false, 'fecha_inicio' => '2026-09-21',
        'estado' => 'ACTIVO', 'creado_por_user_id' => $admin->id,
    ]);

    $migracion->up();

    $copias = ConfiguracionPagoModel::whereNotNull('user_id')->get();

    expect($copias->pluck('user_id')->all())->toBe([$juan->id])
        ->and((float) $copias[0]->monto_base)->toBe(1500.0)
        ->and($copias[0]->fecha_inicio->format('Y-m-d'))->toBe('2026-09-21')
        ->and(ConfiguracionPagoModel::find($porRol)->estado)->toBe('ACTIVO')
        ->and(ConfiguracionPagoModel::where('user_id', $sinFicha->id)->exists())->toBeFalse()
        ->and(AnalistaCalidadModel::count())->toBe(1);
});

// --------------------------------------------------------------------------
// Proveedor: precio base y descuento por la semana OBSERVADA
// --------------------------------------------------------------------------

it('crea la configuración de proveedor por litros, semanal y sin guardar el precio en la configuración', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, aplicaCalidad: true, precio: 1.70);

    $configuracion = ConfiguracionPagoModel::firstOrFail();

    expect($configuracion->tipo_beneficiario)->toBe('PROVEEDOR')
        ->and($configuracion->rol_id)->toBeNull()
        ->and($configuracion->user_id)->toBeNull()
        ->and($configuracion->tipo_calculo)->toBe('POR_LITROS')
        ->and($configuracion->periodicidad)->toBe('SEMANAL')
        ->and($configuracion->monto_base)->toBeNull()
        ->and($configuracion->aplica_calidad)->toBeTrue()
        ->and($configuracion->creado_por_user_id)->toBe($admin->id);
});

it('no se crea una segunda configuración general de proveedores por error', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);

    expect(fn () => pagos_configurar('PROVEEDOR', null, '2026-10-01', $admin, precio: 1.80))
        ->toThrow(DomainException::class, 'Ya existe una configuración de pago para los proveedores');

    expect(ConfiguracionPagoModel::count())->toBe(1);
});

it('el precio base configurado queda como precio de leche vigente desde la fecha de inicio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);

    $precio = PrecioLecheModel::firstOrFail();
    $proveedor = app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'))['proveedor'];

    expect((float) $precio->precio_litro)->toBe(1.7)
        ->and($precio->fecha_inicio->format('Y-m-d'))->toBe('2026-09-01')
        ->and($precio->fecha_fin)->toBeNull()
        ->and($precio->estado)->toBe('ACTIVO')
        ->and($proveedor['precioBase'])->toBe(1.7)
        ->and($proveedor['estado'])->toBe('ACTIVO');
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
        ->and($repo->buscarVigente(new DateTimeImmutable('2026-10-15'))->precioLitro())->toBe(1.8)
        ->and(ConfiguracionPagoModel::count())->toBe(2);
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

it('el descuento de la semana OBSERVADA se guarda con vigencia en reglas_pago_calidad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70, ajustes: [
        'OBSERVADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '-0.20'],
    ]);

    $reglas = ReglaPagoCalidadModel::orderBy('resultado_calidad')->get()->keyBy('resultado_calidad');
    $proveedor = app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'))['proveedor'];

    expect($reglas->keys()->all())->toBe(['ACEPTADA', 'OBSERVADA', 'RECHAZADA'])
        ->and((float) $reglas['OBSERVADA']->valor_ajuste)->toBe(-0.2)
        ->and((float) $reglas['ACEPTADA']->valor_ajuste)->toBe(0.0)
        ->and((float) $reglas['RECHAZADA']->valor_ajuste)->toBe(0.0)
        ->and($proveedor['observada'])->toBe(['tipo' => 'MONTO', 'valor' => 0.2]);
});

it('ACEPTADA es siempre precio normal: un ajuste positivo anterior se cierra como historial y no se suma', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 1.70, new DateTimeImmutable('2026-09-21'));
    fixture_regla_pago('OBSERVADA', 'MONTO', -0.20, new DateTimeImmutable('2026-09-21'));
    fixture_regla_pago('RECHAZADA', 'MONTO', 0.0, new DateTimeImmutable('2026-09-21'));

    pagos_configurar('PROVEEDOR', null, '2026-10-25', $admin, precio: 1.70, ajustes: [
        'ACEPTADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '1.70'],
    ]);

    $aceptadas = ReglaPagoCalidadModel::where('resultado_calidad', 'ACEPTADA')->orderBy('fecha_inicio')->get();

    expect($aceptadas)->toHaveCount(2)
        ->and((float) $aceptadas[0]->valor_ajuste)->toBe(1.7)
        ->and($aceptadas[0]->fecha_fin->format('Y-m-d'))->toBe('2026-10-24')
        ->and((float) $aceptadas[1]->valor_ajuste)->toBe(0.0);
});

it('cambiar el descuento OBSERVADA abre una nueva vigencia y no reescribe la anterior', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin, 1.70);
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);
    $configuracion = ConfiguracionPagoModel::firstOrFail();

    app(ActualizarConfiguracionPago::class)->execute($configuracion->id, null, true, new DateTimeImmutable('2026-10-01'), null, $admin->id, precioBaseLitro: 1.70, ajustes: [
        'OBSERVADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => '-0.25'],
    ]);

    $observadas = ReglaPagoCalidadModel::where('resultado_calidad', 'OBSERVADA')->orderBy('fecha_inicio')->get();

    expect($observadas)->toHaveCount(2)
        ->and((float) $observadas[0]->valor_ajuste)->toBe(-0.3)
        ->and($observadas[0]->fecha_fin->format('Y-m-d'))->toBe('2026-09-30')
        ->and((float) $observadas[1]->valor_ajuste)->toBe(-0.25)
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
