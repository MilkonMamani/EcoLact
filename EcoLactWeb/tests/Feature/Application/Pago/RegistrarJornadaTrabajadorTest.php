<?php

use App\Application\Pago\GenerarObligacionesPersonal;
use App\Application\Pago\RecalcularProgresoObligacionPersonal;
use App\Application\Pago\RegistrarJornadaTrabajador;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/** Abre la obligación EN_CURSO del mes de un acopiador (sueldo S/900 => valor por día S/30) y la devuelve. */
function ventana_en_curso(User $admin, string $nombre = 'Rusman', string $alta = '2026-01-15'): ObligacionPagoModel
{
    $persona = pagos_personal('ACOPIADOR', $nombre, alta: $alta);
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    return ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail();
}

it('CASO 1: trabajador activo todo el mes sin faltas acumula el sueldo mensual completo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);

    expect($ventana->estado)->toBe('EN_CURSO')
        ->and((float) $ventana->valor_dia)->toBe(30.0)
        ->and((float) $ventana->acumulado)->toBe(900.0)
        ->and((float) $ventana->monto_calculado)->toBe(900.0);
});

it('CASO 2: registra una jornada y el acumulado del mes en curso sigue reflejando el sueldo completo (sin faltas)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $identidad = TrabajadorModel::where('user_id', $ventana->user_id)->firstOrFail();

    pagos_jornada($identidad->id, '2026-09-10', $admin->id);

    $ventana->refresh();

    expect($ventana->estado)->toBe('EN_CURSO')
        ->and((int) $ventana->dias_trabajados)->toBe(1)
        ->and((float) $ventana->valor_dia)->toBe(30.0)
        ->and((float) $ventana->acumulado)->toBe(900.0)
        ->and((float) $ventana->monto_calculado)->toBe(900.0);
});

it('CASO 4: una falta descuenta exactamente un valor día del sueldo del mes completo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $identidad = TrabajadorModel::where('user_id', $ventana->user_id)->firstOrFail();

    pagos_falta($identidad->id, $ventana->rol_id, '2026-09-12');
    app(RecalcularProgresoObligacionPersonal::class)->execute(app(ObligacionPagoRepository::class)->buscarPorId($ventana->id));

    $ventana->refresh();

    expect((float) $ventana->acumulado)->toBe(870.0)
        ->and((float) $ventana->monto_calculado)->toBe(870.0);
});

it('CASO 5: un trabajador que ingresa a mitad de mes no genera faltas por los días previos a su ingreso', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    // Ingresa el 15 de septiembre: no estuvo activo todo el mes.
    $ventana = ventana_en_curso($admin, 'Diana', alta: '2026-09-15');
    $identidad = TrabajadorModel::where('user_id', $ventana->user_id)->firstOrFail();

    expect((float) $ventana->acumulado)->toBe(0.0)
        ->and(JornadaPersonalModel::where('trabajador_id', $identidad->id)->count())->toBe(0);

    pagos_jornada($identidad->id, '2026-09-15', $admin->id);
    pagos_jornada($identidad->id, '2026-09-16', $admin->id);

    $ventana->refresh();
    expect((int) $ventana->dias_trabajados)->toBe(2)
        ->and((float) $ventana->acumulado)->toBe(60.0);
});

it('CASO 6: un trabajador parcial nunca acumula más que el sueldo mensual aunque el mes tenga 31 días', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin, 'Diana', alta: '2026-09-15');
    $identidad = TrabajadorModel::where('user_id', $ventana->user_id)->firstOrFail();

    for ($dia = 15; $dia <= 30; $dia++) {
        $this->travelTo(sprintf('2026-09-%02d 10:00:00', $dia));
        pagos_jornada($identidad->id, sprintf('2026-09-%02d', $dia), $admin->id);
    }

    $ventana->refresh();

    // 16 días presentes x 30 = 480 (nunca supera 900, el sueldo mensual).
    expect((int) $ventana->dias_trabajados)->toBe(16)
        ->and((float) $ventana->acumulado)->toBe(480.0)
        ->and((float) $ventana->acumulado)->toBeLessThan(900.0);
});

it('CASO 12: una obligación EN_CURSO nunca puede pagarse, aunque tenga acumulado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);

    expect(fn () => app(RegistrarPagoObligacion::class)->execute(
        obligacionId: $ventana->id,
        metodoPago: MetodoPago::EFECTIVO,
        numeroOperacion: null,
        fechaPago: new DateTimeImmutable('2026-09-20'),
        observacion: null,
        comprobante: null,
        registradoPorUserId: $admin->id,
        hoy: new DateTimeImmutable('2026-09-20'),
    ))->toThrow(DomainException::class, 'ya fue registrado');

    expect($ventana->fresh()->estado)->toBe('EN_CURSO')
        ->and(PagoModel::count())->toBe(0);
});

it('rechaza una jornada duplicada en la misma fecha (validación de la aplicación)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $identidad = TrabajadorModel::where('user_id', $ventana->user_id)->firstOrFail();

    pagos_jornada($identidad->id, '2026-09-10', $admin->id);

    expect(fn () => pagos_jornada($identidad->id, '2026-09-10', $admin->id))
        ->toThrow(DomainException::class, 'Ya se registró');

    expect(JornadaPersonalModel::where('trabajador_id', $identidad->id)->count())->toBe(1);
});

it('el índice único de la base de datos también impide dos jornadas en la misma fecha', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $identidad = TrabajadorModel::where('user_id', $ventana->user_id)->firstOrFail();

    JornadaPersonalModel::create(['trabajador_id' => $identidad->id, 'rol_id' => $ventana->rol_id, 'fecha' => '2026-09-10']);

    expect(fn () => JornadaPersonalModel::create(['trabajador_id' => $identidad->id, 'rol_id' => $ventana->rol_id, 'fecha' => '2026-09-10']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rechaza registrar un día trabajado en el futuro', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $identidad = TrabajadorModel::where('user_id', $ventana->user_id)->firstOrFail();

    expect(fn () => app(RegistrarJornadaTrabajador::class)->execute(
        trabajadorId: $identidad->id,
        fecha: new DateTimeImmutable('2026-09-21'),
        observacion: null,
        registradoPorUserId: $admin->id,
    ))->toThrow(DomainException::class, 'futuro');
});

it('CASO 17: un usuario con role_id de ACOPIADOR pero sin ficha real nunca genera trabajador ni jornada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $huerfano = fixture_usuario('ACOPIADOR', 'acopiador.sin-ficha');
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);

    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    expect(TrabajadorModel::where('user_id', $huerfano->id)->exists())->toBeFalse()
        ->and(ObligacionPagoModel::where('user_id', $huerfano->id)->exists())->toBeFalse();
});

it('una obligación ya PAGADA nunca se recalcula, aunque existan jornadas nuevas en su rango', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $identidad = TrabajadorModel::where('user_id', $ventana->user_id)->firstOrFail();

    // Fuerza el cierre (mes completo, sin faltas -> sueldo completo) para poder pagar.
    $ventana->update(['estado' => 'PENDIENTE']);

    app(RegistrarPagoObligacion::class)->execute(
        obligacionId: $ventana->id,
        metodoPago: MetodoPago::EFECTIVO,
        numeroOperacion: null,
        fechaPago: new DateTimeImmutable('2026-09-20'),
        observacion: null,
        comprobante: null,
        registradoPorUserId: $admin->id,
        hoy: new DateTimeImmutable('2026-09-20'),
    );

    $pagada = $ventana->fresh();
    expect($pagada->estado)->toBe('PAGADO')->and((float) $pagada->monto_calculado)->toBe(900.0);

    pagos_jornada($identidad->id, '2026-09-10', $admin->id);
    app(RecalcularProgresoObligacionPersonal::class)->execute(
        app(ObligacionPagoRepository::class)->buscarPorId($pagada->id)
    );

    expect($pagada->fresh()->estado)->toBe('PAGADO')
        ->and((float) $pagada->fresh()->monto_calculado)->toBe(900.0);
});
