<?php

use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

function modal_pagos_proveedor(string $nombres, string $apellidos): ProveedorModel
{
    $proveedor = fixture_proveedor(ZonaModel::firstOrCreate(['nombre' => 'Collana 1'], ['estado' => 'ACTIVO']));
    $proveedor->update(['nombres' => $nombres, 'apellidos' => $apellidos]);

    return $proveedor;
}

function modal_pagos_obligacion(ProveedorModel $proveedor, string $inicio, string $fin, float $litros, float $precio = 1.70): ObligacionPagoModel
{
    $total = round($litros * $precio, 2);

    return ObligacionPagoModel::create([
        'tipo_beneficiario' => 'PROVEEDOR',
        'proveedor_id' => $proveedor->id,
        'beneficiario_nombre' => trim($proveedor->nombres.' '.$proveedor->apellidos),
        'periodo_inicio' => $inicio,
        'periodo_fin' => $fin,
        'periodicidad' => 'SEMANAL',
        'concepto' => 'Pago semanal a proveedor',
        'base_calculo' => ['litros_pagables' => $litros, 'precio_base_litro' => $precio, 'subtotal' => $total],
        'monto_calculado' => $total,
        'estado' => 'PENDIENTE',
        'clave_unicidad' => 'modal-'.$proveedor->id.'-'.$inicio.'-'.$fin,
    ]);
}

/**
 * Milkon (proveedor 1): Semana 38 parcial (14-15/09, 30 L, pagado), Semana 38 completa (10-16/09, 20 L, pagado),
 * Semana 39 (17-23/09, 10 L, pendiente) y Semana 37 (03-09/09, 5 L, pendiente).
 * Juana (proveedor 2): un pago pagado en la Semana 38 que NUNCA debe verse en el historial de Milkon.
 *
 * @return array{admin: User, milkon: ProveedorModel, juana: ProveedorModel, parcial: ObligacionPagoModel, completa: ObligacionPagoModel, sem39: ObligacionPagoModel, sem37: ObligacionPagoModel, ajena: ObligacionPagoModel}
 */
function modal_pagos_escenario(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $milkon = modal_pagos_proveedor('Milkon', 'Mamani');
    $juana = modal_pagos_proveedor('Juana', 'Quispe');

    $parcial = modal_pagos_obligacion($milkon, '2026-09-14', '2026-09-15', 30.0);
    $completa = modal_pagos_obligacion($milkon, '2026-09-10', '2026-09-16', 20.0);
    $sem39 = modal_pagos_obligacion($milkon, '2026-09-17', '2026-09-23', 10.0);
    $sem37 = modal_pagos_obligacion($milkon, '2026-09-03', '2026-09-09', 5.0);
    $ajena = modal_pagos_obligacion($juana, '2026-09-10', '2026-09-16', 40.0);

    // Se pagan por el flujo real de Pagar a proveedores (queda el snapshot del comprobante).
    test()->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$parcial->id, $completa->id, $ajena->id]])
        ->assertSessionHas('success');

    return compact('admin', 'milkon', 'juana', 'parcial', 'completa', 'sem39', 'sem37', 'ajena');
}

/** @return array<int, int> ids de pago que muestra la tabla del historial, en orden */
function modal_pagos_ids(array $json): array
{
    preg_match_all('/data-pago-fila="(\d+)"/', $json['html'], $m);

    return array_map('intval', $m[1]);
}

function modal_pagos_get(ProveedorModel $proveedor, array $parametros = []): TestResponse
{
    return test()->getJson(route('admin.proveedores.pagos.index', ['proveedor' => $proveedor->id] + $parametros))->assertOk();
}

// CASO 1
it('el historial del proveedor devuelve exclusivamente sus pagos', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    $json = modal_pagos_get($e['milkon'])->json();

    expect(modal_pagos_ids($json))->toEqualCanonicalizing([$e['sem39']->id, $e['parcial']->id, $e['completa']->id, $e['sem37']->id])
        ->and($json['total'])->toBe(4);
});

// CASOS 2 y 11
it('nunca aparecen pagos de otros proveedores, ni siquiera manipulando el request', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    $json = modal_pagos_get($e['milkon'], ['semana' => '2026-09-10', 'estado' => 'todos', 'proveedor_id' => $e['juana']->id, 'proveedor' => $e['juana']->id])->json();

    expect(modal_pagos_ids($json))->not->toContain($e['ajena']->id)
        ->and(modal_pagos_ids(modal_pagos_get($e['juana'])->json()))->toBe([$e['ajena']->id]);

    // El pago de otro proveedor tampoco se sirve por la URL de este proveedor.
    $this->getJson(route('admin.proveedores.pagos.show', [$e['milkon']->id, $e['ajena']->id]))->assertNotFound();
    $this->getJson(route('admin.proveedores.pagos.show', [$e['juana']->id, $e['ajena']->id]))->assertOk();
});

// CASO 3 y 10
it('Semana Todos abarca todas las semanas del proveedor y las semanas del combo no se repiten', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    $json = modal_pagos_get($e['milkon'], ['semana' => 'todos'])->json();

    expect($json['total'])->toBe(4)
        ->and(array_column($json['semanas'], 'etiqueta'))->toBe(['Semana 39', 'Semana 38', 'Semana 37'])
        ->and(array_column($json['semanas'], 'valor'))->toBe(['2026-09-17', '2026-09-10', '2026-09-03'])
        ->and($json['semana'])->toBe('todos');
});

// CASO 4
it('una semana específica solo devuelve los registros de esa semana, incluido el periodo parcial', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    expect(modal_pagos_ids(modal_pagos_get($e['milkon'], ['semana' => '2026-09-10'])->json()))
        ->toEqualCanonicalizing([$e['parcial']->id, $e['completa']->id])
        ->and(modal_pagos_ids(modal_pagos_get($e['milkon'], ['semana' => '2026-09-17'])->json()))->toBe([$e['sem39']->id]);
});

// CASOS 5, 6 y 7
it('filtra por estado y combina semana con estado', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    expect(modal_pagos_ids(modal_pagos_get($e['milkon'], ['estado' => 'pagados'])->json()))->toEqualCanonicalizing([$e['parcial']->id, $e['completa']->id])
        ->and(modal_pagos_ids(modal_pagos_get($e['milkon'], ['estado' => 'pendientes'])->json()))->toEqualCanonicalizing([$e['sem39']->id, $e['sem37']->id])
        ->and(modal_pagos_ids(modal_pagos_get($e['milkon'], ['semana' => '2026-09-10', 'estado' => 'pagados'])->json()))->toEqualCanonicalizing([$e['parcial']->id, $e['completa']->id])
        ->and(modal_pagos_ids(modal_pagos_get($e['milkon'], ['semana' => '2026-09-10', 'estado' => 'pendientes'])->json()))->toBe([]);

    modal_pagos_get($e['milkon'], ['semana' => '2026-09-10', 'estado' => 'pendientes'])
        ->assertJsonPath('total', 0);
});

it('muestra la base de cálculo, el periodo real, el monto y el estado de cada fila', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    $html = modal_pagos_get($e['milkon'], ['semana' => '2026-09-10'])->json('html');

    expect($html)->toContain('30.00 L &times; S/ 1.70')->toContain('S/ 51.00')->toContain('14/09/2026 - 15/09/2026')
        ->toContain('20.00 L &times; S/ 1.70')->toContain('S/ 34.00')->toContain('10/09/2026 - 16/09/2026')
        ->toContain('Semana 38')->toContain('Proveedor')->toContain('Pagado')->toContain('Ver detalle')
        ->toContain('Mostrando 2 de 2 registros');
});

// CASO 8
it('un pago histórico usa el snapshot: ni la lista ni el detalle se recalculan con precios de hoy', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    // Después del pago, el precio "cambia": el histórico debe seguir mostrando S/ 1.70 y S/ 34.00.
    $e['completa']->update(['monto_calculado' => 36.0, 'base_calculo' => ['litros_pagables' => 20, 'precio_base_litro' => 1.80, 'subtotal' => 36.0]]);

    $html = modal_pagos_get($e['milkon'], ['semana' => '2026-09-10'])->json('html');
    expect($html)->toContain('20.00 L &times; S/ 1.70')->toContain('S/ 34.00')->not->toContain('1.80')->not->toContain('36.00');

    $detalle = $this->getJson(route('admin.proveedores.pagos.show', [$e['milkon']->id, $e['completa']->id]))->assertOk();
    expect($detalle->json('estado'))->toBe('PAGADO')
        ->and($detalle->json('html'))->toContain('S/ 1.70')->toContain('S/ 34.00')->toContain('Pagado el')->not->toContain('1.80')->not->toContain('36.00');
});

it('el detalle de un pago pendiente muestra el comprobante con los datos del proveedor', function () {
    $e = modal_pagos_escenario();

    $json = $this->actingAs($e['admin'])->getJson(route('admin.proveedores.pagos.show', [$e['milkon']->id, $e['sem39']->id]))->assertOk()->json();

    expect($json['estado'])->toBe('PENDIENTE')->and($json['semana'])->toBe('Semana 39')
        ->and($json['html'])->toContain('Milkon Mamani')->toContain('Collana 1')->toContain('17/09/2026 - 23/09/2026')->toContain('S/ 17.00');
});

// CASO 9
it('un proveedor sin pagos devuelve un estado vacío válido', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sinPagos = modal_pagos_proveedor('Ana', 'Rojas');
    $this->actingAs($admin);

    $json = modal_pagos_get($sinPagos)->json();

    expect($json['total'])->toBe(0)->and($json['semanas'])->toBe([])
        ->and($json['html'])->toContain('Aún no hay pagos registrados')
        ->toContain('Este proveedor todavía no tiene pagos o liquidaciones disponibles.')
        ->not->toContain('<table');
});

it('con filtros que no coinciden muestra "No se encontraron pagos" y no el vacío general', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    $html = modal_pagos_get($e['milkon'], ['semana' => '2026-09-10', 'estado' => 'pendientes'])->json('html');

    expect($html)->toContain('No se encontraron pagos')->toContain('No existen registros para los filtros seleccionados.')
        ->not->toContain('Aún no hay pagos registrados');
});

it('pagina el historial respetando proveedor y filtros', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $proveedor = modal_pagos_proveedor('Milkon', 'Mamani');
    foreach (range(0, 9) as $i) {
        modal_pagos_obligacion($proveedor, date('Y-m-d', strtotime('2026-06-04 +'.($i * 7).' days')), date('Y-m-d', strtotime('2026-06-10 +'.($i * 7).' days')), 10.0 + $i);
    }
    $this->actingAs($admin);

    $primera = modal_pagos_get($proveedor)->json();
    $segunda = modal_pagos_get($proveedor, ['page' => 2])->json();
    $fuera = modal_pagos_get($proveedor, ['page' => 99])->json();

    expect(modal_pagos_ids($primera))->toHaveCount(8)->and(modal_pagos_ids($segunda))->toHaveCount(2)
        ->and(array_intersect(modal_pagos_ids($primera), modal_pagos_ids($segunda)))->toBe([])
        ->and($primera['html'])->toContain('Mostrando 1-8 de 10 registros')->toContain('data-pagina="2"')
        ->and($segunda['html'])->toContain('Mostrando 9-10 de 10 registros')
        ->and($fuera['pagina'])->toBe(2);

    // La paginación respeta el filtro de estado: no hay pagados, así que no hay nada que paginar.
    modal_pagos_get($proveedor, ['estado' => 'pagados', 'page' => 2])->assertJsonPath('total', 0);
});

it('abrir el recurso del modal directamente en el navegador lleva al detalle del proveedor, no a otra pantalla', function () {
    $e = modal_pagos_escenario();
    $this->actingAs($e['admin']);

    $this->get(route('admin.proveedores.pagos.index', $e['milkon']->id))->assertRedirect(route('admin.proveedores.show', $e['milkon']->id));
    $this->get(route('admin.proveedores.pagos.show', [$e['milkon']->id, $e['completa']->id]))->assertRedirect(route('admin.proveedores.show', $e['milkon']->id));
});

it('solo el administrador consulta el historial de pagos de un proveedor', function () {
    $e = modal_pagos_escenario();
    $proveedor = fixture_usuario('PROVEEDOR');

    $this->actingAs($proveedor)->getJson(route('admin.proveedores.pagos.index', $e['milkon']->id))->assertForbidden();
    $this->actingAs($proveedor)->getJson(route('admin.proveedores.pagos.show', [$e['milkon']->id, $e['completa']->id]))->assertForbidden();
    auth()->logout();
    $this->getJson(route('admin.proveedores.pagos.index', $e['milkon']->id))->assertUnauthorized();
});

it('el detalle del proveedor abre "Ver pagos" en un modal sin enlazar al módulo de pagos', function () {
    $e = modal_pagos_escenario();

    $html = $this->actingAs($e['admin'])->get(route('admin.proveedores.show', $e['milkon']->id))->assertOk()
        ->assertSee('Pagos de Milkon Mamani')
        ->assertSee('Historial de pagos realizados al proveedor. Consulta los comprobantes por semana.')
        ->assertSee('Este proveedor tiene 4 pagos registrados (2 pendientes de pago).')
        ->assertSee('data-pagos-abrir', false)
        ->assertSee('modal-pago-detalle', false)
        ->assertSee(route('admin.proveedores.pagos.index', $e['milkon']->id), false)
        ->getContent();

    // "Ver pagos" es un botón (sin href): no navega al módulo general de pagos.
    $posicion = strpos($html, 'data-pagos-abrir');
    $inicioEtiqueta = strrpos(substr($html, 0, $posicion), '<');
    $etiqueta = substr($html, $inicioEtiqueta, strpos($html, '>', $posicion) - $inicioEtiqueta);
    expect($etiqueta)->toStartWith('<button')->not->toContain('href=');

    $this->get(route('admin.proveedores.show', $e['juana']->id))->assertSee('Pagos de Juana Quispe')->assertSee('Este proveedor tiene 1 pago registrado.');
});
