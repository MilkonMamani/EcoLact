<?php

use App\Application\Pago\GenerarObligacionesPersonal;
use App\Infrastructure\Persistence\Models\AcopiadorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';
require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('busca por nombre, apellido, DNI o teléfono y filtra por estado y zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $collana = fixture_zona('Collana 1');
    $faon = fixture_zona('Faon');
    $vanessa = fixture_acopiador($collana, '10000001');
    $vanessa->update(['nombres' => 'Vanessa', 'apellidos' => 'Huanca', 'telefono' => '951111222']);
    $luis = fixture_acopiador($faon, '10000002');
    $luis->update(['nombres' => 'Luis', 'apellidos' => 'Mamani', 'estado' => 'INACTIVO']);

    $this->actingAs($admin);

    $this->get(route('admin.acopiadores.index', ['q' => 'Vanessa']))->assertSee('10000001')->assertDontSee('10000002');
    $this->get(route('admin.acopiadores.index', ['q' => 'Huanca']))->assertSee('10000001')->assertDontSee('10000002');
    $this->get(route('admin.acopiadores.index', ['q' => '10000002']))->assertSee('10000002')->assertDontSee('10000001');
    $this->get(route('admin.acopiadores.index', ['q' => '951111']))->assertSee('10000001')->assertDontSee('10000002');
    $this->get(route('admin.acopiadores.index', ['estado' => 'INACTIVO']))->assertSee('10000002')->assertDontSee('10000001');
    $this->get(route('admin.acopiadores.index', ['zona_id' => $collana->id]))->assertSee('10000001')->assertDontSee('10000002');
});

it('el listado se pagina en el servidor conservando los filtros', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    foreach (range(1, 13) as $i) {
        fixture_acopiador($zona, sprintf('2000%04d', $i));
    }

    $this->actingAs($admin)->get(route('admin.acopiadores.index', ['estado' => 'ACTIVO']))
        ->assertSee('Mostrando 1 a 12 de 13 acopiadores')
        ->assertSee(e(route('admin.acopiadores.index', ['estado' => 'ACTIVO', 'pagina' => 2])), false);
});

it('la pestaña Entregas muestra el nombre real del proveedor y la identificación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);
    $proveedor->update(['nombres' => 'Rosa', 'apellidos' => 'Quispe']);
    $entrega = fixture_entrega($proveedor, $acopiador, 18.5);

    $this->actingAs($admin)->get(route('admin.acopiadores.panel', $acopiador->id))
        ->assertOk()
        ->assertSeeInOrder(['Resumen', 'Entregas', 'Zona', 'Pagos'])
        ->assertSeeInOrder(['Fecha', 'Proveedor', 'Zona', 'Litros', 'Identificación', 'Estado', 'Ver'])
        ->assertSeeInOrder(['Rosa Quispe', 'Collana 1', '18.50', 'DNI', 'Pendiente análisis'])
        ->assertSee(route('admin.entregas.show', $entrega->id), false)
        ->assertDontSee('Proveedor #'.$proveedor->id);
});

it('cambiar la zona desde la pestaña Zona cierra el tramo anterior y abre uno nuevo con el motivo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $collana = fixture_zona('Collana 1');
    $faon = fixture_zona('Faon');
    $acopiador = fixture_acopiador($collana);
    AcopiadorZonaHistorialModel::create(['acopiador_id' => $acopiador->id, 'zona_id' => $collana->id, 'fecha_inicio' => '2026-09-01', 'motivo' => 'Asignación inicial']);
    $pantalla = route('admin.acopiadores.index', ['acopiador' => $acopiador->id]);

    $this->actingAs($admin)
        ->from($pantalla)
        ->patch(route('admin.acopiadores.update', $acopiador->id), ['zona_id' => $faon->id, 'motivo_cambio_zona' => 'Cambio operativo'])
        ->assertRedirect($pantalla)
        ->assertSessionHas('success');

    expect($acopiador->fresh()->zona_id)->toBe($faon->id);

    $tramos = AcopiadorZonaHistorialModel::where('acopiador_id', $acopiador->id)->orderBy('id')->get();
    expect($tramos)->toHaveCount(2)
        ->and($tramos[0]->fecha_fin)->not->toBeNull()
        ->and($tramos[1]->zona_id)->toBe($faon->id)
        ->and($tramos[1]->fecha_fin)->toBeNull()
        ->and($tramos[1]->motivo)->toBe('Cambio operativo');

    // Tabla y panel muestran la misma zona.
    $this->get($pantalla)
        ->assertSeeInOrder(['data-fila-zona>Faon', 'Zona actual', 'Faon'], false)
        ->assertSeeInOrder(['Historial de zonas', 'Faon', 'Actual', 'Cambio operativo', 'Collana 1', '01/09/2026', 'Asignación inicial']);
});

it('el formulario de información personal no permite editar el DNI', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = fixture_acopiador(fixture_zona('Collana 1'));

    $html = $this->actingAs($admin)->get(route('admin.acopiadores.panel', $acopiador->id))->getContent();
    $modal = substr($html, strpos($html, 'id="modal-editar-acopiador-'.$acopiador->id.'"'));
    $modal = substr($modal, 0, strpos($modal, '</form>'));

    expect($modal)->toContain('name="nombres"')->toContain('name="apellidos"')->toContain('name="telefono"')->not->toContain('name="dni"');
});

/** Contenido de la pestaña Pagos del panel del acopiador. */
function pestana_pagos_acopiador(string $html): string
{
    $pagos = substr($html, strpos($html, 'data-fichas-tab-panel="pagos"'));

    return substr($pagos, 0, strpos($pagos, '</section>'));
}

/** Acopiador con sueldo configurado y su obligación EN_CURSO de septiembre 2026 ya abierta. */
function acopiador_con_sueldo(User $admin, float $sueldo = 900.0): User
{
    $persona = pagos_personal('ACOPIADOR', 'Rusman');
    pagos_configurar('ACOPIADOR', $sueldo, '2026-08-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));

    return $persona;
}

it('la pestaña Asistencia pinta el calendario del mes y su resumen', function () {
    $this->travelTo('2026-09-03 10:00:00'); // 05:00 en Lima del 3: solo el 1 y el 2 ya cerraron.
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = acopiador_con_sueldo($admin);
    $identidad = TrabajadorModel::where('user_id', $persona->id)->firstOrFail();
    pagos_jornada($identidad->id, '2026-09-01', $admin->id);
    pagos_falta($identidad->id, $persona->role_id, '2026-09-02');

    $html = $this->actingAs($admin)->get(route('admin.acopiadores.panel', $persona->acopiador->id))
        ->assertOk()
        ->assertSeeInOrder(['Resumen', 'Entregas', 'Zona', 'Asistencia', 'Pagos'])
        ->getContent();
    $asistencia = substr($html, strpos($html, 'data-fichas-tab-panel="asistencia"'));
    $asistencia = substr($asistencia, 0, strpos($asistencia, '</section>'));

    expect($asistencia)
        ->toContain('Septiembre 2026')
        ->toContain('data-fecha="2026-09-01"')
        ->toContain('data-estado="PRESENTE"')
        ->toContain('data-estado="FALTA"')
        ->toContain('Resumen del mes');
    expect(preg_replace('/\s+/', ' ', strip_tags($asistencia)))
        ->toContain('Asistencias✓ 1')
        ->toContain('Faltas✕ 1')
        ->not->toContain('PRESENTE');
});

it('la pestaña Pagos es un resumen de consulta: sin calendario, registro manual, QR ni acciones de pago', function () {
    $this->travelTo('2026-09-20 10:00:00');
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = acopiador_con_sueldo($admin);

    $html = $this->actingAs($admin)->get(route('admin.acopiadores.panel', $persona->acopiador->id))->assertOk()->getContent();

    expect(pestana_pagos_acopiador($html))->toContain('data-pago-mensual')->toContain('Pago mensual')
        ->not->toContain('data-control-periodo')
        ->not->toContain('data-attendance-calendar')
        ->not->toContain('Registrar asistencia manual')
        ->not->toContain('data-qr-code')
        ->not->toContain('Registrar pago');
});

it('con 0 asistencias y mes completo muestra el sueldo sin faltas descontadas, en soles', function () {
    $this->travelTo('2026-09-01 10:00:00'); // 05:00 en Lima del 1: ningún día del mes cerró todavía.
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = acopiador_con_sueldo($admin, 1500);
    $url = route('admin.acopiadores.panel', $persona->acopiador->id);

    expect(pestana_pagos_acopiador($this->actingAs($admin)->get($url)->getContent()))
        ->toContain('En curso')
        ->toContain('Septiembre 2026')
        ->toContain('Periodo: 01/09/2026 – 30/09/2026')
        ->not->toContain('$ ');

    $this->get($url)->assertSeeInOrder([
        'Sueldo mensual', 'S/ 1,500.00',
        'Valor por día', 'S/ 50.00',
        'Asistencias', '0',
        'Faltas', '0',
        'Acumulado', 'Sueldo mensual menos faltas registradas.', 'S/ 1,500.00',
        'Bonificación', 'S/ 0.00',
        'Descuentos', 'S/ 0.00',
        'Total final', 'S/ 1,500.00',
    ]);
});

it('las asistencias y faltas salen de las jornadas y el acumulado del snapshot de la obligación', function () {
    $this->travelTo('2026-09-04 10:00:00'); // 05:00 en Lima del 4: cerraron del 1 al 3.
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = acopiador_con_sueldo($admin, 900);
    $identidad = TrabajadorModel::where('user_id', $persona->id)->firstOrFail();
    pagos_falta($identidad->id, $persona->role_id, '2026-09-03');
    pagos_jornada($identidad->id, '2026-09-01', $admin->id);
    pagos_jornada($identidad->id, '2026-09-02', $admin->id);

    expect((float) ObligacionPagoModel::where('user_id', $persona->id)->value('acumulado'))->toBe(870.0);

    $this->actingAs($admin)->get(route('admin.acopiadores.panel', $persona->acopiador->id))->assertSeeInOrder([
        'Valor por día', 'S/ 30.00',
        'Asistencias', '2',
        'Faltas', '1',
        'Acumulado', 'S/ 870.00',
        'Total final', 'S/ 870.00',
    ]);
});

it('si ingresó a mitad de mes el acumulado es proporcional a sus asistencias', function () {
    $this->travelTo('2026-09-20 10:00:00');
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal('ACOPIADOR', 'Diana', '2026-09-15');
    pagos_configurar('ACOPIADOR', 1500, '2026-08-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-09-20'));
    $identidad = TrabajadorModel::where('user_id', $persona->id)->firstOrFail();
    pagos_jornada($identidad->id, '2026-09-15', $admin->id);
    pagos_jornada($identidad->id, '2026-09-16', $admin->id);

    $this->actingAs($admin)->get(route('admin.acopiadores.panel', $persona->acopiador->id))->assertSeeInOrder([
        'Asistencias', '2',
        'Acumulado', 'Pago proporcional según los días trabajados desde su fecha de ingreso (15/09/2026).', 'S/ 100.00',
        'Total final', 'S/ 100.00',
    ]);
});

it('un mes ya pagado muestra Pagado con su bonificación, descuento y total final reales', function () {
    $this->travelTo('2026-08-10 10:00:00');
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = pagos_personal('ACOPIADOR', 'Rusman');
    pagos_configurar('ACOPIADOR', 900, '2026-08-01', $admin);
    app(GenerarObligacionesPersonal::class)->execute(new DateTimeImmutable('2026-08-10'));
    $identidad = TrabajadorModel::where('user_id', $persona->id)->firstOrFail();
    foreach (range(1, 5) as $dia) {
        pagos_jornada($identidad->id, sprintf('2026-08-%02d', $dia), $admin->id);
    }
    $agosto = pagos_cerrar_mes(ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail());

    $this->travelTo('2026-09-20 10:00:00');
    $this->actingAs($admin)->postJson(route('admin.pagos.trabajadores.ajuste', $agosto->id), ['bonificacion' => 100, 'descuento' => 40])->assertOk();
    $this->post(route('admin.pagos.trabajadores.pagar', $agosto->id), ['metodo_pago' => 'EFECTIVO', 'fecha_pago' => '2026-09-20']);
    $agosto->refresh();
    expect($agosto->estado)->toBe('PAGADO')->and((float) $agosto->acumulado)->toBe(120.0);

    $url = route('admin.acopiadores.panel', [$persona->acopiador->id, 'mes' => '2026-08']);

    expect(pestana_pagos_acopiador($this->get($url)->assertOk()->getContent()))
        ->toContain('Pagado')->toContain('Agosto 2026')->not->toContain('En curso');

    $this->get($url)->assertSeeInOrder([
        'Acumulado', 'S/ '.number_format((float) $agosto->acumulado, 2),
        'Bonificación', 'S/ 100.00',
        'Descuentos', 'S/ 40.00',
        'Total final', 'S/ '.number_format((float) $agosto->acumulado + 60, 2),
    ]);
});

it('el selector de mes navega con ?mes= y no ofrece meses futuros', function () {
    $this->travelTo('2026-09-20 10:00:00');
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = acopiador_con_sueldo($admin);
    $url = route('admin.acopiadores.index', ['acopiador' => $persona->acopiador->id]);

    expect(pestana_pagos_acopiador($this->actingAs($admin)->get($url)->getContent()))
        ->toContain('aria-label="Mes anterior"')->toContain('mes=2026-08')
        ->not->toContain('aria-label="Mes siguiente"');

    expect(pestana_pagos_acopiador($this->get($url.'&mes=2026-07')->getContent()))
        ->toContain('Julio 2026')->toContain('Periodo: 01/07/2026 – 31/07/2026')
        ->toContain('No hay registro de nómina para este mes.')
        ->toContain('aria-label="Mes siguiente"')->toContain('mes=2026-08');
});

it('enlaza al módulo de pagos con el trabajador y el mes seleccionados', function () {
    $this->travelTo('2026-09-20 10:00:00');
    $admin = fixture_usuario('ADMINISTRADOR');
    $persona = acopiador_con_sueldo($admin);
    $obligacion = ObligacionPagoModel::where('user_id', $persona->id)->firstOrFail();
    $destino = route('admin.pagos.trabajadores.index', ['periodo' => '2026-09', 'trabajador' => $obligacion->id]);

    expect(pestana_pagos_acopiador($this->actingAs($admin)->get(route('admin.acopiadores.panel', $persona->acopiador->id))->getContent()))
        ->toContain('Ver en módulo de pagos')->toContain(e($destino));

    $this->get($destino)->assertOk()->assertSee('Rusman');
});

it('desactivar pide confirmación y reactivar exige zona y el botón Reactivar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_acopiador(fixture_zona('Collana 1'));

    $this->actingAs($admin)->get(route('admin.acopiadores.index'))
        ->assertSeeInOrder(['Desactivar acopiador', '¿Deseas desactivar a', 'El acopiador dejará de estar disponible para registrar nuevas entregas. Su historial se conservará.', 'Sí, desactivar'])
        ->assertSeeInOrder(['Reactivar acopiador', 'Selecciona la zona a la que se reincorpora', 'Zona', 'Reactivar']);
});

it('el listado no hace consultas N+1 aunque crezcan los acopiadores y sus entregas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $proveedor = fixture_proveedor($zona);
    $primero = fixture_acopiador($zona);
    fixture_entrega($proveedor, $primero, 10.0);

    $contarConsultas = function () use ($admin): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.acopiadores.index'))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $contarConsultas(); // la primera petición hace trabajo único (p. ej. preparar el periodo de pagos)
    $conUno = $contarConsultas();

    foreach (range(1, 6) as $i) {
        fixture_entrega($proveedor, fixture_acopiador(fixture_zona('Zona '.$i)), 5.0);
        fixture_entrega($proveedor, $primero, 5.0);
    }

    expect($contarConsultas())->toBe($conUno);
});
