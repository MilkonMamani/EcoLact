<?php

use App\Application\Pago\GenerarObligacionesPersonal;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * Regla del sueldo mensual (opción A, confirmada por el negocio):
 * - Quien trabaja TODO el mes sin faltar cobra el sueldo de Configuración de
 *   pagos completo, tenga el mes 28, 30 o 31 días (no se paga por días).
 * - Cada falta descuenta un valor por día = sueldo ÷ 30, igual en cualquier mes.
 */

/**
 * Cierra el mes de un acopiador con sueldo S/ 1,500 (valor por día S/ 50) que
 * estuvo activo todo el mes y faltó en $faltas; los demás días asistió.
 *
 * @param  list<string>  $faltas
 */
function cerrar_mes_con_faltas(string $mes, array $faltas): ObligacionPagoModel
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal('ACOPIADOR', 'Juan', '2026-01-01');
    pagos_configurar('ACOPIADOR', 1500, '2026-01-01', $admin);

    $inicio = new DateTimeImmutable($mes.'-01');
    $fin = $inicio->modify('last day of this month');
    test()->travelTo($inicio->format('Y-m-d').' 15:00:00');
    app(GenerarObligacionesPersonal::class)->execute($inicio);

    $trabajador = TrabajadorModel::where('user_id', $persona->id)->firstOrFail();

    for ($dia = $inicio; $dia <= $fin; $dia = $dia->modify('+1 day')) {
        $falta = in_array($dia->format('Y-m-d'), $faltas, true);

        JornadaPersonalModel::create([
            'trabajador_id' => $trabajador->id,
            'rol_id' => $persona->role_id,
            'fecha' => $dia->format('Y-m-d'),
            'estado' => ($falta ? EstadoJornada::FALTA : EstadoJornada::PRESENTE)->value,
            'origen' => ($falta ? OrigenJornada::AUTOMATICO : OrigenJornada::MANUAL)->value,
            'registrado_por_user_id' => $falta ? null : $admin->id,
        ]);
    }

    test()->travelTo($fin->modify('+1 day')->format('Y-m-d').' 15:00:00');

    return pagos_cerrar_mes(ObligacionPagoModel::where('user_id', $persona->id)->sole(), $admin->id);
}

it('sin faltas cobra el sueldo mensual completo, tenga el mes 28 o 31 días', function (string $mes) {
    $obligacion = cerrar_mes_con_faltas($mes, []);

    expect((float) $obligacion->acumulado)->toBe(1500.0)
        ->and((float) $obligacion->valor_dia)->toBe(50.0);
})->with(['febrero (28 días)' => '2026-02', 'marzo (31 días)' => '2026-03', 'abril (30 días)' => '2026-04']);

it('cada falta descuenta un valor por día (sueldo ÷ 30), igual en cualquier mes', function (string $mes, array $faltas, float $esperado) {
    expect((float) cerrar_mes_con_faltas($mes, $faltas)->acumulado)->toBe($esperado);
})->with([
    'febrero con 1 falta' => ['2026-02', ['2026-02-10'], 1450.0],
    'marzo (31) con 1 falta' => ['2026-03', ['2026-03-10'], 1450.0],
    'marzo (31) con 3 faltas' => ['2026-03', ['2026-03-03', '2026-03-10', '2026-03-17'], 1350.0],
]);
