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
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/** Abre la ventana EN_CURSO de un acopiador (sueldo S/900 => valor por día S/30) y la devuelve. */
function ventana_en_curso(User $admin, string $nombre = 'Rusman'): ObligacionPagoModel
{
    $persona = pagos_personal('ACOPIADOR', $nombre);
    pagos_configurar('ACOPIADOR', 900, '2026-09-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    return ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail();
}

it('CASO 2: registra una jornada y sube el acumulado del periodo en curso', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $rolId = RoleModel::where('nombre', 'ACOPIADOR')->value('id');

    pagos_jornada($ventana->user_id, $rolId, '2026-09-10', $admin->id);

    $ventana->refresh();

    expect($ventana->estado)->toBe('EN_CURSO')
        ->and((int) $ventana->dias_trabajados)->toBe(1)
        ->and((float) $ventana->valor_dia)->toBe(30.0)
        ->and((float) $ventana->acumulado)->toBe(30.0)
        ->and((float) $ventana->monto_calculado)->toBe(30.0);
});

it('CASO 4: la jornada número 30 transiciona la ventana a PENDIENTE con el acumulado completo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $rolId = RoleModel::where('nombre', 'ACOPIADOR')->value('id');
    $this->travelTo('2026-09-30 10:00:00');

    for ($dia = 1; $dia <= 29; $dia++) {
        pagos_jornada($ventana->user_id, $rolId, sprintf('2026-09-%02d', $dia), $admin->id);
    }

    $ventana->refresh();
    expect($ventana->estado)->toBe('EN_CURSO')->and((int) $ventana->dias_trabajados)->toBe(29);

    pagos_jornada($ventana->user_id, $rolId, '2026-09-30', $admin->id);

    $ventana->refresh();
    expect($ventana->estado)->toBe('PENDIENTE')
        ->and((int) $ventana->dias_trabajados)->toBe(30)
        ->and((float) $ventana->acumulado)->toBe(900.0)
        ->and((float) $ventana->monto_calculado)->toBe(900.0);
});

it('CASO 3: la pantalla no puede pagar un periodo EN_CURSO (menos de 30 días)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $rolId = RoleModel::where('nombre', 'ACOPIADOR')->value('id');

    for ($dia = 1; $dia <= 10; $dia++) {
        pagos_jornada($ventana->user_id, $rolId, sprintf('2026-09-%02d', $dia), $admin->id);
    }

    expect(fn () => app(RegistrarPagoObligacion::class)->execute(
        obligacionId: $ventana->id,
        metodoPago: MetodoPago::EFECTIVO,
        numeroOperacion: null,
        fechaPago: new DateTimeImmutable('2026-09-20'),
        observacion: null,
        comprobante: null,
        registradoPorUserId: $admin->id,
        hoy: new DateTimeImmutable('2026-09-20'),
    ))->toThrow(DomainException::class);

    expect($ventana->fresh()->estado)->toBe('EN_CURSO');
});

it('CASO 3b: defensa en profundidad — aunque alguien manipule el estado a PENDIENTE por fuera, con menos de 30 días registrados el pago se rechaza igual', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $rolId = RoleModel::where('nombre', 'ACOPIADOR')->value('id');

    for ($dia = 1; $dia <= 10; $dia++) {
        pagos_jornada($ventana->user_id, $rolId, sprintf('2026-09-%02d', $dia), $admin->id);
    }

    // Simula una manipulación directa de la petición HTTP / de la fila: el
    // estado dice PENDIENTE, pero solo hay 10 días reales trabajados.
    $ventana->fresh()->update(['estado' => 'PENDIENTE']);

    expect(fn () => app(RegistrarPagoObligacion::class)->execute(
        obligacionId: $ventana->id,
        metodoPago: MetodoPago::EFECTIVO,
        numeroOperacion: null,
        fechaPago: new DateTimeImmutable('2026-09-20'),
        observacion: null,
        comprobante: null,
        registradoPorUserId: $admin->id,
        hoy: new DateTimeImmutable('2026-09-20'),
    ))->toThrow(DomainException::class, '30 días');

    expect(PagoModel::count())->toBe(0);
});

it('rechaza una jornada duplicada en la misma fecha (validación de la aplicación)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $rolId = RoleModel::where('nombre', 'ACOPIADOR')->value('id');

    pagos_jornada($ventana->user_id, $rolId, '2026-09-10', $admin->id);

    expect(fn () => pagos_jornada($ventana->user_id, $rolId, '2026-09-10', $admin->id))
        ->toThrow(DomainException::class, 'Ya se registró');

    expect($ventana->fresh()->dias_trabajados)->toBe(1);
});

it('el índice único de la base de datos también impide dos jornadas en la misma fecha', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);

    JornadaPersonalModel::create(['user_id' => $ventana->user_id, 'rol_id' => $ventana->rol_id, 'fecha' => '2026-09-10']);

    expect(fn () => JornadaPersonalModel::create(['user_id' => $ventana->user_id, 'rol_id' => $ventana->rol_id, 'fecha' => '2026-09-10']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rechaza registrar un día trabajado en el futuro', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);

    expect(fn () => app(RegistrarJornadaTrabajador::class)->execute(
        userId: $ventana->user_id,
        rolId: $ventana->rol_id,
        fecha: new DateTimeImmutable('2026-09-21'),
        observacion: null,
        registradoPorUserId: $admin->id,
        hoy: new DateTimeImmutable('2026-09-20'),
    ))->toThrow(DomainException::class, 'futuro');
});

it('rechaza registrar una jornada cuando no hay ningún periodo abierto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $huerfano = fixture_usuario('ACOPIADOR', 'acopiador.sin-config');

    expect(fn () => app(RegistrarJornadaTrabajador::class)->execute(
        userId: $huerfano->id,
        rolId: $huerfano->role_id,
        fecha: new DateTimeImmutable('2026-09-10'),
        observacion: null,
        registradoPorUserId: $admin->id,
        hoy: new DateTimeImmutable('2026-09-20'),
    ))->toThrow(DomainException::class, 'periodo abierto');
});

it('una obligación ya PAGADA nunca se recalcula, aunque existan jornadas nuevas en su rango', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventana = ventana_en_curso($admin);
    $rolId = RoleModel::where('nombre', 'ACOPIADOR')->value('id');
    $this->travelTo('2026-09-30 10:00:00');

    for ($dia = 1; $dia <= 30; $dia++) {
        pagos_jornada($ventana->user_id, $rolId, sprintf('2026-09-%02d', $dia), $admin->id);
    }

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

    app(RecalcularProgresoObligacionPersonal::class)->execute(
        app(ObligacionPagoRepository::class)->buscarPorId($pagada->id)
    );

    expect($pagada->fresh()->estado)->toBe('PAGADO')
        ->and((float) $pagada->fresh()->monto_calculado)->toBe(900.0);
});
