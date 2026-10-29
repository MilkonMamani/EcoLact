<?php

use App\Infrastructure\Mantenimiento\SimuladorAcopio;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AcopiadorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\MuestreoCalidadModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // 23:00 UTC = 18:00 en Lima del 28/10/2026: todas las horas del plan ya pasaron.
    $this->travelTo('2026-10-28 23:00:00');
});

/**
 * Las cuatro zonas, sus acopiadores (con asistencia), la analista de Calidad y los
 * catorce proveedores del plan, tal como existen en ecolactv1.
 */
function simulacion_escenario(): void
{
    $zonas = [1 => ['Collana 1', 'Yanil', 'Perez'], 2 => ['Collana 2', 'Alicia', 'Ramos'], 3 => ['Faon', 'Yoel', 'Turpo'], 4 => ['Yasin', 'Lenin', 'Murga']];

    foreach ($zonas as $id => [$nombre, $nombres, $apellidos]) {
        $zona = ZonaModel::forceCreate(['id' => $id, 'nombre' => $nombre, 'estado' => 'ACTIVO']);
        $acopiador = fixture_acopiador($zona);
        $acopiador->update(['nombres' => $nombres, 'apellidos' => $apellidos]);
        AcopiadorZonaHistorialModel::create(['acopiador_id' => $acopiador->id, 'zona_id' => $id, 'fecha_inicio' => '2026-10-28', 'motivo' => 'Registro inicial del acopiador.']);
        fixture_presente_hoy(User::findOrFail($acopiador->user_id), '2026-10-28');
    }

    $proveedores = [
        1 => ['71000032' => 'Aarón Pérez Huanca', '71000046' => 'Ainara Cutipa Chambi', '71000096' => 'Ainoa Quisbert Ticona', '71000055' => 'Alba Cutipa Aquino'],
        2 => ['71000208' => 'Abril Cutipa Quisbert', '71000227' => 'Adela Pérez Sucari', '71000207' => 'Adelardo Quisbert Choque', '71000197' => 'Adelia Pérez Quispe'],
        3 => ['71000347' => 'Adolfo Pérez Calcina', '71000253' => 'Adriana Cutipa Mendoza', '71000334' => 'África Cutipa Ccallo', '71000251' => 'Ágata Pérez Castro'],
        4 => ['71000475' => 'Abraham Cutipa Pérez', '71000467' => 'Adalberto Pérez López'],
    ];

    foreach ($proveedores as $zonaId => $lista) {
        foreach ($lista as $dni => $nombre) {
            [$nombres, $apellidos] = explode(' ', $nombre, 2);
            fixture_proveedor(ZonaModel::findOrFail($zonaId), (string) $dni)->update(['nombres' => $nombres, 'apellidos' => $apellidos]);
        }
    }

    $analista = fixture_usuario('CALIDAD', 'analista.simulacion');
    DB::table('analistas_calidad')->insert([
        'user_id' => $analista->id, 'dni' => fixture_next_dni(), 'nombres' => 'Diana', 'apellidos' => 'Huanca',
        'estado' => 'ACTIVO', 'fecha_ingreso' => '2026-10-28', 'created_at' => now(), 'updated_at' => now(),
    ]);
    fixture_presente_hoy($analista, '2026-10-28');
}

it('registra por QR 12 500 L del 28/10/2026 y deja una muestra pendiente por zona, sin resultados de laboratorio', function () {
    simulacion_escenario();

    $this->artisan('ecolact:simular-acopio', ['--apply' => true, '--force' => true])->assertSuccessful();

    $lote = EntregaModel::where('token_idempotencia', 'like', SimuladorAcopio::LOTE.'-%');
    expect((clone $lote)->count())->toBe(19)
        ->and(round((float) (clone $lote)->sum('litros'), 2))->toBe(12500.0)
        ->and((clone $lote)->where('metodo_identificacion', '!=', 'QR')->exists())->toBeFalse()
        ->and((clone $lote)->where('estado', '!=', 'PENDIENTE_ANALISIS')->exists())->toBeFalse()
        ->and((clone $lote)->where('litros', '<=', 0)->exists())->toBeFalse()
        ->and((clone $lote)->pluck('zona_id', 'zona_id')->sort()->values()->all())->toBe([1, 2, 3, 4]);

    // Cada entrega la registró el acopiador de la zona del proveedor.
    foreach ((clone $lote)->get() as $entrega) {
        $acopiador = AcopiadorModel::findOrFail($entrega->acopiador_id);
        expect($acopiador->zona_id)->toBe($entrega->zona_id)
            ->and($entrega->registrado_por_user_id)->toBe($acopiador->user_id)
            ->and($entrega->observacion)->toContain(SimuladorAcopio::LOTE);
    }

    $litrosPorZona = (clone $lote)->groupBy('zona_id')->selectRaw('zona_id, SUM(litros) as litros')->pluck('litros', 'zona_id')->map(fn ($l) => round((float) $l, 2))->all();
    expect($litrosPorZona)->toBe([1 => 3600.0, 2 => 3800.0, 3 => 3300.0, 4 => 1800.0]);

    $dnisMuestreados = MuestreoCalidadModel::where('muestreos_calidad.estado', 'SELECCIONADA')
        ->join('entregas', 'entregas.id', '=', 'muestreos_calidad.entrega_id')
        ->join('proveedores', 'proveedores.id', '=', 'entregas.proveedor_id')
        ->orderBy('proveedores.zona_id')->pluck('proveedores.dni')->all();
    expect($dnisMuestreados)->toBe(['71000032', '71000208', '71000347', '71000475'])
        ->and(DB::table('analisis_calidad')->count())->toBe(0)
        ->and(DB::table('movimientos_caja')->count())->toBe(0)
        ->and(DB::table('pagos')->count())->toBe(0);
});

it('volver a ejecutarla no duplica entregas ni muestras', function () {
    simulacion_escenario();

    $this->artisan('ecolact:simular-acopio', ['--apply' => true, '--force' => true])->assertSuccessful();
    $this->artisan('ecolact:simular-acopio', ['--apply' => true, '--force' => true])->assertSuccessful();

    expect(EntregaModel::count())->toBe(19)
        ->and(round((float) EntregaModel::sum('litros'), 2))->toBe(12500.0)
        ->and(MuestreoCalidadModel::count())->toBe(4);
});

it('sin --apply solo verifica y no registra nada', function () {
    simulacion_escenario();

    $this->artisan('ecolact:simular-acopio')->assertSuccessful();

    expect(EntregaModel::count())->toBe(0)->and(MuestreoCalidadModel::count())->toBe(0);
});

it('no registra nada si un proveedor del plan no pertenece a la zona esperada', function () {
    simulacion_escenario();
    ProveedorModel::where('dni', '71000467')->update(['zona_id' => 1]);

    $this->artisan('ecolact:simular-acopio', ['--apply' => true, '--force' => true])
        ->expectsOutputToContain('Adalberto Pérez López (DNI 71000467) no pertenece a Yasin.')
        ->assertFailed();

    expect(EntregaModel::count())->toBe(0)->and(MuestreoCalidadModel::count())->toBe(0);
});

it('no registra nada si un acopiador no tiene asistencia ese día', function () {
    simulacion_escenario();
    $lenin = AcopiadorModel::where('nombres', 'Lenin')->firstOrFail();
    DB::table('jornadas_personal')->whereIn('trabajador_id', DB::table('trabajadores')->where('user_id', $lenin->user_id)->pluck('id'))->delete();

    $this->artisan('ecolact:simular-acopio', ['--apply' => true, '--force' => true])
        ->expectsOutputToContain('Lenin Murga no tiene asistencia válida')
        ->assertFailed();

    expect(EntregaModel::count())->toBe(0);
});
