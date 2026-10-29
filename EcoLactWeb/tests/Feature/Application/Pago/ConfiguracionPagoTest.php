<?php

use App\Application\Pago\ActualizarConfiguracionPago;
use App\Application\Pago\Contracts\SueldosPorRolQuery;
use App\Application\Pago\GenerarPagosPendientes;
use App\Application\Pago\ObtenerConfiguracionesPago;
use App\Application\Pago\RegistrarConfiguracionPago;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\AuditoriaModel;
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
 * @return array<string, array<string, mixed>> filas de la pestaña Trabajadores (una por rol), por etiqueta del rol
 */
function filas_de_roles(string $hoy = '2026-09-20'): array
{
    return collect(app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable($hoy))['trabajadores']['roles'])
        ->keyBy('rol')->all();
}

function configurar_sueldo_rol(string $rol, float $sueldo, string $desde, int $adminId, ?string $observacion = null): void
{
    app(RegistrarConfiguracionPago::class)->execute(TipoBeneficiarioPago::PERSONAL, fixture_rol($rol)->id, $sueldo, false, new DateTimeImmutable($desde), $observacion, $adminId);
}

function sueldo_del_rol(string $rol): ConfiguracionPagoModel
{
    return ConfiguracionPagoModel::where('rol_id', fixture_rol($rol)->id)->whereNull('user_id')->orderByDesc('fecha_inicio')->firstOrFail();
}

// --------------------------------------------------------------------------
// Trabajadores: un sueldo base por rol laboral
// --------------------------------------------------------------------------

it('lista los roles laborales remunerables desde la base de datos, sin PROVEEDOR, con su personal activo', function () {
    fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Juan');
    pagos_personal('ACOPIADOR', 'Pedro');
    pagos_personal('ACOPIADOR', 'Inactivo', estado: 'INACTIVO');
    pagos_personal('CALIDAD', 'David');
    fixture_usuario('PROVEEDOR');

    $filas = filas_de_roles();

    expect(array_keys($filas))->toBe(['Acopiador', 'Administrador', 'Analista de calidad'])
        ->and($filas['Acopiador']['personal'])->toBe(2)
        ->and($filas['Administrador']['personal'])->toBe(1)
        ->and($filas['Acopiador']['estado'])->toBe('SIN_CONFIGURAR')
        ->and($filas['Acopiador']['sueldo'])->toBeNull();
});

it('los indicadores cuentan roles laborales, configurados y pendientes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR');
    pagos_personal('VENTAS');
    configurar_sueldo_rol('ACOPIADOR', 1500, '2026-09-01', $admin->id);
    configurar_sueldo_rol('VENTAS', 1800, '2026-11-01', $admin->id);

    $resumen = app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'))['trabajadores']['resumen'];

    expect($resumen)->toBe(['roles' => 3, 'configurados' => 1, 'pendientes' => 2]);
});

it('configurar el sueldo de un rol lo guarda una sola vez, sin trabajador, y calcula el valor diario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR', 'Juan');
    pagos_personal('ACOPIADOR', 'Pedro');

    configurar_sueldo_rol('ACOPIADOR', 1500, '2026-09-01', $admin->id);

    $configuracion = ConfiguracionPagoModel::sole();
    $fila = filas_de_roles()['Acopiador'];

    expect($configuracion->user_id)->toBeNull()
        ->and($configuracion->rol->nombre)->toBe('ACOPIADOR')
        ->and($configuracion->tipo_calculo)->toBe('SUELDO_FIJO')
        ->and($configuracion->periodicidad)->toBe('MENSUAL')
        ->and($configuracion->creado_por_user_id)->toBe($admin->id)
        ->and($fila['estado'])->toBe('ACTIVO')
        ->and($fila['sueldo'])->toBe(1500.0)
        ->and($fila['valorDia'])->toBe(50.0)
        ->and($fila['vigenteDesde'])->toBe('2026-09-01');
});

it('el valor diario es el sueldo mensual entre 30, redondeado a céntimos solo para mostrarlo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('CALIDAD');

    configurar_sueldo_rol('CALIDAD', 2000, '2026-09-01', $admin->id);

    expect(filas_de_roles()['Analista de calidad']['valorDia'])->toBe(66.67);
});

it('todos los trabajadores del rol heredan el mismo sueldo, también los que se registran después', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $yanil = pagos_personal('ACOPIADOR', 'Yanil');
    configurar_sueldo_rol('ACOPIADOR', 1500, '2026-09-01', $admin->id);
    $alicia = pagos_personal('ACOPIADOR', 'Alicia');
    $lenin = pagos_personal('ACOPIADOR', 'Lenin');

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    $obligaciones = ObligacionPagoModel::orderBy('user_id')->get();

    expect($obligaciones->pluck('user_id')->all())->toBe([$yanil->id, $alicia->id, $lenin->id])
        ->and($obligaciones->map(fn (ObligacionPagoModel $o): float => (float) $o->base_calculo['sueldo_base'])->unique()->all())->toBe([1500.0])
        ->and($obligaciones->pluck('configuracion_pago_id')->unique()->all())->toBe([ConfiguracionPagoModel::sole()->id])
        ->and(ConfiguracionPagoModel::count())->toBe(1);
});

it('un sueldo individual del modelo anterior ya no se usa: manda el sueldo del rol', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    pagos_sueldo_individual_anterior($juan, 2500, '2026-09-01', $admin);
    $pedro = pagos_personal('VENTAS', 'Pedro');
    pagos_sueldo_individual_anterior($pedro, 1800, '2026-09-01', $admin);
    configurar_sueldo_rol('ACOPIADOR', 1500, '2026-09-01', $admin->id);

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect(ObligacionPagoModel::pluck('user_id')->all())->toBe([$juan->id])
        ->and((float) ObligacionPagoModel::sole()->base_calculo['sueldo_base'])->toBe(1500.0)
        ->and(filas_de_roles()['Personal de ventas']['estado'])->toBe('SIN_CONFIGURAR');
});

it('un sueldo individual del modelo anterior no se puede editar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    $individual = pagos_sueldo_individual_anterior($juan, 1500, '2026-09-01', $admin);

    expect(fn () => app(ActualizarConfiguracionPago::class)->execute($individual, 1600.0, false, new DateTimeImmutable('2026-10-01'), 'Aumento', $admin->id))
        ->toThrow(DomainException::class, 'se configura por rol');

    expect(ConfiguracionPagoModel::count())->toBe(1);
});

it('PROVEEDOR y los roles no remunerables no pueden tener sueldo', function (string $rol) {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_rol($rol);

    if ($rol === 'VENTAS') {
        RoleModel::where('nombre', 'VENTAS')->update(['es_remunerable' => false]);
    }

    expect(fn () => configurar_sueldo_rol($rol, 1000, '2026-09-01', $admin->id))
        ->toThrow(DomainException::class, 'rol laboral remunerable');

    expect(ConfiguracionPagoModel::count())->toBe(0);
})->with(['PROVEEDOR', 'VENTAS']);

it('un rol que deja de ser remunerable no genera pagos aunque tenga sueldo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('VENTAS');
    pagos_configurar('VENTAS', 1000, '2026-09-01', $admin);
    RoleModel::where('nombre', 'VENTAS')->update(['es_remunerable' => false]);

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect(ObligacionPagoModel::count())->toBe(0);
});

it('no permite dos sueldos activos del mismo rol con vigencias que se cruzan', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    configurar_sueldo_rol('ACOPIADOR', 1000, '2026-09-01', $admin->id);

    expect(fn () => configurar_sueldo_rol('ACOPIADOR', 1200, '2026-10-01', $admin->id))
        ->toThrow(DomainException::class, 'Ya existe una configuración de pago para el rol Acopiador');

    expect(ConfiguracionPagoModel::count())->toBe(1);
});

it('rechaza un sueldo cero o negativo', function (float $sueldo) {
    $admin = fixture_usuario('ADMINISTRADOR');

    expect(fn () => configurar_sueldo_rol('VENTAS', $sueldo, '2026-09-01', $admin->id))
        ->toThrow(DomainException::class, 'mayor a cero');

    expect(ConfiguracionPagoModel::count())->toBe(0);
})->with([0.0, -50.0]);

it('configurar un sueldo queda en auditorías con el rol, el valor, la vigencia, el responsable y el motivo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    configurar_sueldo_rol('ACOPIADOR', 1500, '2026-09-01', $admin->id, 'Sueldo inicial.');
    $original = sueldo_del_rol('ACOPIADOR');

    app(ActualizarConfiguracionPago::class)->execute($original->id, 1650.0, false, new DateTimeImmutable('2026-11-01'), 'Aumento anual.', $admin->id);

    $auditorias = AuditoriaModel::where('tabla_afectada', 'configuraciones_pago')->orderBy('id')->get();

    expect($auditorias->pluck('accion')->all())->toBe(['CONFIGURAR_SUELDO_ROL', 'NUEVA_VIGENCIA_SUELDO_ROL'])
        ->and($auditorias->pluck('user_id')->unique()->all())->toBe([$admin->id])
        ->and($auditorias[0]->datos_nuevos)->toMatchArray(['rol' => 'ACOPIADOR', 'sueldo_mensual' => 1500, 'vigente_desde' => '2026-09-01'])
        ->and($auditorias[1]->datos_anteriores)->toMatchArray(['rol' => 'ACOPIADOR', 'sueldo_mensual' => 1500, 'vigente_desde' => '2026-09-01'])
        ->and($auditorias[1]->datos_nuevos)->toMatchArray(['rol' => 'ACOPIADOR', 'sueldo_mensual' => 1650, 'vigente_desde' => '2026-11-01'])
        ->and($auditorias[1]->motivo)->toBe('Aumento anual.');
});

// --------------------------------------------------------------------------
// Vigencias: cambiar un sueldo nunca reescribe el pasado
// --------------------------------------------------------------------------

it('cambiar el sueldo del rol con una fecha posterior abre una nueva versión y deja la anterior en el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    configurar_sueldo_rol('ACOPIADOR', 1500, '2026-09-01', $admin->id);
    $original = ConfiguracionPagoModel::sole();

    app(ActualizarConfiguracionPago::class)->execute($original->id, 1650.0, false, new DateTimeImmutable('2026-10-25'), 'Aumento.', $admin->id);

    $detalle = app(SueldosPorRolQuery::class)->detalle(fixture_rol('ACOPIADOR')->id, new DateTimeImmutable('2026-10-26'));

    expect(ConfiguracionPagoModel::count())->toBe(2)
        ->and((float) $original->refresh()->monto_base)->toBe(1500.0)
        ->and($original->fecha_fin->format('Y-m-d'))->toBe('2026-10-24')
        ->and($detalle['sueldo'])->toBe(1650.0)
        ->and($detalle['vigenteDesde'])->toBe('2026-10-25')
        ->and(array_map(fn (array $v): array => [$v['sueldo'], $v['desde'], $v['estado']], $detalle['historial']))
        ->toBe([[1650.0, '2026-10-25', 'VIGENTE'], [1500.0, '2026-09-01', 'ANTERIOR']]);
});

it('un cambio de sueldo del rol no toca el pago ya generado del periodo anterior', function () {
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
        ->and($nueva->rolId())->toBe($original->rol_id)
        ->and($nueva->userId())->toBeNull()
        ->and($nueva->fechaFin())->toBeNull()
        ->and((float) ObligacionPagoModel::firstOrFail()->valor_dia)->toBe(round(1000 / 30, 2))
        ->and((float) ObligacionPagoModel::firstOrFail()->base_calculo['sueldo_base'])->toBe(1000.0);
});

it('un mes nuevo usa el nuevo sueldo del rol y el mes anterior conserva el suyo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $juan = pagos_personal('ACOPIADOR', 'Juan');
    pagos_configurar('ACOPIADOR', 1500, '2026-09-01', $admin);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));
    app(ActualizarConfiguracionPago::class)->execute(ConfiguracionPagoModel::sole()->id, 1650.0, false, new DateTimeImmutable('2026-10-01'), 'Aumento.', $admin->id);
    $nuevo = pagos_personal('ACOPIADOR', 'Nuevo', alta: '2026-10-01');

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-10-05'));

    $deJuan = ObligacionPagoModel::where('user_id', $juan->id)->sole();
    $delNuevo = ObligacionPagoModel::where('user_id', $nuevo->id)->sole();

    expect($deJuan->periodo_inicio->format('Y-m-d'))->toBe('2026-09-01')
        ->and((float) $deJuan->base_calculo['sueldo_base'])->toBe(1500.0)
        ->and($delNuevo->periodo_inicio->format('Y-m-d'))->toBe('2026-10-01')
        ->and((float) $delNuevo->base_calculo['sueldo_base'])->toBe(1650.0)
        ->and((float) $delNuevo->valor_dia)->toBe(55.0);
});

it('un pago confirmado no cambia aunque luego cambie el sueldo del rol', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_personal('ACOPIADOR');
    pagos_configurar('ACOPIADOR', 1000, '2026-09-01', $admin);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));
    ObligacionPagoModel::query()->update(['estado' => 'PAGADO', 'acumulado' => 1000, 'monto_calculado' => 1000]);

    app(ActualizarConfiguracionPago::class)->execute(ConfiguracionPagoModel::sole()->id, 2000.0, false, new DateTimeImmutable('2026-10-01'), 'Aumento.', $admin->id);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-25'));

    $pagada = ObligacionPagoModel::sole();

    expect($pagada->estado)->toBe('PAGADO')
        ->and((float) $pagada->monto_calculado)->toBe(1000.0)
        ->and((float) $pagada->base_calculo['sueldo_base'])->toBe(1000.0);
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

    app(ActualizarConfiguracionPago::class)->execute($original->id, 1350.0, false, new DateTimeImmutable('2026-10-01'), 'Error de digitación.', $admin->id);

    expect(ConfiguracionPagoModel::count())->toBe(1)
        ->and((float) $original->refresh()->monto_base)->toBe(1350.0)
        ->and(ObligacionPagoModel::count())->toBe(0)
        ->and(AuditoriaModel::where('accion', 'CORREGIR_SUELDO_ROL')->sole()->datos_anteriores['sueldo_mensual'])->toEqual(1300);
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
    configurar_sueldo_rol('ACOPIADOR', 1500, '2026-09-01', $admin->id);
    app(ActualizarConfiguracionPago::class)->execute(ConfiguracionPagoModel::sole()->id, 1800.0, false, new DateTimeImmutable('2026-11-01'), null, $admin->id);

    $detalle = app(SueldosPorRolQuery::class)->detalle(fixture_rol('ACOPIADOR')->id, new DateTimeImmutable('2026-09-20'));

    expect($detalle['estado'])->toBe('ACTIVO')
        ->and($detalle['sueldo'])->toBe(1500.0)
        ->and($detalle['vigenteDesde'])->toBe('2026-09-01')
        ->and($detalle['proxima'])->toMatchArray(['sueldo' => 1800.0, 'desde' => '2026-11-01', 'estado' => 'PROGRAMADO']);
});

it('un sueldo con vigencia futura se muestra como programado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    configurar_sueldo_rol('CALIDAD', 1100, '2026-11-01', $admin->id);

    expect(filas_de_roles()['Analista de calidad']['estado'])->toBe('PROGRAMADO');
});

it('el detalle del rol lista su personal activo y, como referencia, los sueldos individuales anteriores', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $yanil = pagos_personal('ACOPIADOR', 'Yanil');
    $alicia = pagos_personal('ACOPIADOR', 'Alicia');
    pagos_sueldo_individual_anterior($yanil, 1500, '2026-09-01', $admin);
    pagos_sueldo_individual_anterior($alicia, 1400, '2026-09-01', $admin);

    $detalle = app(SueldosPorRolQuery::class)->detalle(fixture_rol('ACOPIADOR')->id, new DateTimeImmutable('2026-09-20'));

    expect(array_column($detalle['trabajadores'], 'nombre'))->toBe(['Alicia Apellido', 'Yanil Apellido'])
        ->and($detalle['sueldosIndividualesAnteriores'])->toBe([
            ['nombre' => 'Alicia Apellido', 'sueldo' => 1400.0, 'desde' => '2026-09-01'],
            ['nombre' => 'Yanil Apellido', 'sueldo' => 1500.0, 'desde' => '2026-09-01'],
        ])
        ->and($detalle['estado'])->toBe('SIN_CONFIGURAR');
});

it('la configuración general de proveedores no aparece como rol ni se ve afectada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);
    configurar_sueldo_rol('ACOPIADOR', 1500, '2026-09-01', $admin->id);

    $datos = app(ObtenerConfiguracionesPago::class)->execute(new DateTimeImmutable('2026-09-20'));
    $proveedor = ConfiguracionPagoModel::where('tipo_beneficiario', 'PROVEEDOR')->sole();

    expect(collect($datos['trabajadores']['roles'])->pluck('configuracionId')->filter()->values()->all())->toBe([sueldo_del_rol('ACOPIADOR')->id])
        ->and($proveedor->rol_id)->toBeNull()
        ->and($datos['proveedor']['configuracion']->id())->toBe($proveedor->id)
        ->and($datos['proveedor']['precioBase'])->toBe(1.7);
});

// --------------------------------------------------------------------------
// Consolidación: de sueldos individuales a un sueldo por rol (pagos:consolidar-sueldos-por-rol)
// --------------------------------------------------------------------------

it('la consolidación propone el sueldo común de un rol, marca conflictos y en simulación no modifica nada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $yanil = pagos_personal('ACOPIADOR', 'Yanil');
    pagos_personal('ACOPIADOR', 'Alicia');
    pagos_sueldo_individual_anterior($yanil, 1500, '2026-09-01', $admin);
    $diana = pagos_personal('CALIDAD', 'Diana');
    $david = pagos_personal('CALIDAD', 'David');
    pagos_sueldo_individual_anterior($diana, 1800, '2026-09-01', $admin);
    pagos_sueldo_individual_anterior($david, 1700, '2026-09-01', $admin);
    $this->travelTo('2026-09-20');

    $this->artisan('pagos:consolidar-sueldos-por-rol')->assertSuccessful();

    expect(ConfiguracionPagoModel::whereNull('user_id')->count())->toBe(0);

    $this->artisan('pagos:consolidar-sueldos-por-rol', ['--apply' => true, '--force' => true])->assertSuccessful();

    $porRol = ConfiguracionPagoModel::whereNull('user_id')->get();

    expect($porRol)->toHaveCount(1)
        ->and($porRol[0]->rol->nombre)->toBe('ACOPIADOR')
        ->and((float) $porRol[0]->monto_base)->toBe(1500.0)
        ->and($porRol[0]->fecha_inicio->format('Y-m-d'))->toBe('2026-09-01')
        ->and(ConfiguracionPagoModel::whereNotNull('user_id')->where('estado', 'ACTIVO')->count())->toBe(3)
        ->and(filas_de_roles()['Analista de calidad']['estado'])->toBe('SIN_CONFIGURAR');

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect(ObligacionPagoModel::count())->toBe(2);
});

it('la consolidación no toca un rol que ya tiene sueldo por rol', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $yanil = pagos_personal('ACOPIADOR', 'Yanil');
    pagos_sueldo_individual_anterior($yanil, 1500, '2026-09-01', $admin);
    configurar_sueldo_rol('ACOPIADOR', 1600, '2026-09-01', $admin->id);
    $this->travelTo('2026-09-20');

    $this->artisan('pagos:consolidar-sueldos-por-rol', ['--apply' => true, '--force' => true])->assertSuccessful();

    expect(ConfiguracionPagoModel::whereNull('user_id')->pluck('monto_base')->map(fn ($m): float => (float) $m)->all())->toBe([1600.0]);
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
