<?php

use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/**
 * Historial de pagos de proveedores en varias semanas (jueves a miércoles):
 *
 *   Semana 39 (17/09 - 23/09): Luis Calcina  · Collana 1 · 30 L · S/ 51.00 · PENDIENTE
 *   Semana 38 (10/09 - 16/09): Milkon Mamani · Collana 1 · 20 L · S/ 34.00 · PAGADO
 *                              Juana Quispe  · Collana 2 · 40 L · S/ 68.00 · PENDIENTE
 *   Semana 38 (14/09 - 15/09): Rosa Huanca   · Collana 2 · 10 L · S/ 17.00 · PAGADO   (periodo parcial)
 *   Semana 37 (03/09 - 09/09): Pedro Ticona  · Collana 1 · 15 L · S/ 25.50 · PENDIENTE
 *
 * @return array{admin: User, luis: ObligacionPagoModel, milkon: ObligacionPagoModel, juana: ObligacionPagoModel, rosa: ObligacionPagoModel, pedro: ObligacionPagoModel, zonaA: int, zonaB: int}
 */
function historial_pagos_proveedores(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaA = fixture_zona('Collana 1');
    $zonaB = fixture_zona('Collana 2');

    $obligacion = function (string $nombre, ProveedorModel $proveedor, string $inicio, string $fin, float $litros) use ($admin) {
        $total = round($litros * 1.70, 2);

        return ObligacionPagoModel::create([
            'tipo_beneficiario' => 'PROVEEDOR',
            'proveedor_id' => $proveedor->id,
            'beneficiario_nombre' => $nombre,
            'periodo_inicio' => $inicio,
            'periodo_fin' => $fin,
            'periodicidad' => 'SEMANAL',
            'concepto' => 'Pago semanal a proveedor',
            'base_calculo' => ['litros_pagables' => $litros, 'precio_base_litro' => 1.70, 'subtotal' => $total],
            'monto_calculado' => $total,
            'estado' => 'PENDIENTE',
            'clave_unicidad' => 'test-'.$proveedor->id.'-'.$inicio.'-'.$fin,
            'creado_por_user_id' => $admin->id,
        ]);
    };

    $milkon = $obligacion('Milkon Mamani', fixture_proveedor($zonaA), '2026-09-10', '2026-09-16', 20.0);
    $luis = $obligacion('Luis Calcina', fixture_proveedor($zonaA), '2026-09-17', '2026-09-23', 30.0);
    $juana = $obligacion('Juana Quispe', fixture_proveedor($zonaB), '2026-09-10', '2026-09-16', 40.0);
    $rosa = $obligacion('Rosa Huanca', fixture_proveedor($zonaB), '2026-09-14', '2026-09-15', 10.0);
    $pedro = $obligacion('Pedro Ticona', fixture_proveedor($zonaA), '2026-09-03', '2026-09-09', 15.0);

    // Milkon y Rosa se pagan por el flujo real (queda el snapshot del comprobante).
    test()->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$milkon->id, $rosa->id]])
        ->assertSessionHas('success');

    return ['admin' => $admin, 'luis' => $luis, 'milkon' => $milkon, 'juana' => $juana, 'rosa' => $rosa, 'pedro' => $pedro, 'zonaA' => $zonaA->id, 'zonaB' => $zonaB->id];
}

/** Nombres de proveedor visibles en la tabla, en el orden mostrado. */
function nombres_en_tabla(string $html): array
{
    preg_match_all('/<tr data-fila data-id="\d+".*?<td data-proveedor-nombre[^>]*>\s*([^<]+?)\s*(?:<|$)/s', $html, $coincidencias);

    return array_map('trim', $coincidencias[1]);
}

function pantalla_pago(array $parametros = []): array
{
    $respuesta = test()->get(route('admin.pagos.proveedores.index', $parametros))->assertOk();

    return [$respuesta, nombres_en_tabla($respuesta->getContent())];
}

// CASO 1
it('Semana Todos + Estado Todos muestra pendientes y pagados de varias semanas', function () {
    $h = historial_pagos_proveedores();
    $this->actingAs($h['admin']);

    [, $nombres] = pantalla_pago(['semana' => 'todos', 'zona' => '', 'estado' => 'todos']);

    expect($nombres)->toEqualCanonicalizing(['Luis Calcina', 'Milkon Mamani', 'Juana Quispe', 'Rosa Huanca', 'Pedro Ticona']);
});

// CASO 2
it('Semana Todos + Estado Pendientes muestra solo pendientes', function () {
    $h = historial_pagos_proveedores();
    $this->actingAs($h['admin']);

    [, $nombres] = pantalla_pago(['semana' => 'todos', 'estado' => 'pendientes']);

    expect($nombres)->toEqualCanonicalizing(['Luis Calcina', 'Juana Quispe', 'Pedro Ticona']);
});

// CASO 3
it('Semana Todos + Estado Pagados muestra solo pagados', function () {
    $h = historial_pagos_proveedores();
    $this->actingAs($h['admin']);

    [, $nombres] = pantalla_pago(['semana' => 'todos', 'estado' => 'pagados']);

    expect($nombres)->toEqualCanonicalizing(['Milkon Mamani', 'Rosa Huanca']);
});

// CASO 4
it('una semana específica con Estado Todos muestra pendientes y pagados solo de esa semana, incluido su periodo parcial', function () {
    $h = historial_pagos_proveedores();
    $this->actingAs($h['admin']);

    [, $nombres] = pantalla_pago(['semana' => '2026-09-10', 'estado' => 'todos']);

    // Rosa Huanca tiene un periodo parcial (14/09-15/09) dentro de la Semana 38: antes no aparecía en ninguna semana.
    expect($nombres)->toEqualCanonicalizing(['Milkon Mamani', 'Juana Quispe', 'Rosa Huanca']);

    [, $semana39] = pantalla_pago(['semana' => '2026-09-17', 'estado' => 'todos']);
    expect($semana39)->toBe(['Luis Calcina']);
});

// CASOS 5, 6 y 7
it('un proveedor pagado aparece con Estado Todos y Pagados, y no con Pendientes', function () {
    $h = historial_pagos_proveedores();
    $this->actingAs($h['admin']);

    expect(pantalla_pago(['semana' => '2026-09-10', 'estado' => 'todos'])[1])->toContain('Milkon Mamani')
        ->and(pantalla_pago(['semana' => '2026-09-10', 'estado' => 'pagados'])[1])->toContain('Milkon Mamani')
        ->and(pantalla_pago(['semana' => '2026-09-10', 'estado' => 'pendientes'])[1])->not->toContain('Milkon Mamani');
});

it('el estado por defecto es Todos y el combo lista Todos, Pendientes de pago y Pagados', function () {
    $h = historial_pagos_proveedores();

    $respuesta = $this->actingAs($h['admin'])->get(route('admin.pagos.proveedores.index', ['semana' => '2026-09-10']))->assertOk();

    expect(nombres_en_tabla($respuesta->getContent()))->toContain('Milkon Mamani', 'Juana Quispe');
    $respuesta->assertSeeInOrder(['<option value="todos" selected>Todos</option>', 'Pendientes de pago', 'Pagados'], false);
});

// CASO 8
it('la fila de un pagado tiene el checkbox deshabilitado y las de pendientes no', function () {
    $h = historial_pagos_proveedores();

    $html = $this->actingAs($h['admin'])->get(route('admin.pagos.proveedores.index', ['semana' => 'todos']))->assertOk()->getContent();

    $fila = fn (ObligacionPagoModel $o): string => preg_match('/<tr data-fila data-id="'.$o->id.'".*?<\/tr>/s', $html, $m) === 1 ? $m[0] : '';

    expect($fila($h['milkon']))->toContain('data-pendiente="0"')->toMatch('/data-marcar[^>]*disabled/')
        ->and($fila($h['luis']))->toContain('data-pendiente="1"')->not->toMatch('/data-marcar[^>]*disabled/');

    // El servidor tampoco acepta pagarlo otra vez aunque se manipule el formulario.
    $this->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$h['milkon']->id]])->assertSessionHas('error');
    expect(PagoModel::where('obligacion_pago_id', $h['milkon']->id)->count())->toBe(1);
});

// CASO 9 (parte servidor: qué filas son seleccionables; la selección en sí la hace el JS con data-pendiente)
it('el botón Seleccionar pendientes de la zona solo puede tomar filas marcadas como pendientes', function () {
    $h = historial_pagos_proveedores();

    $html = $this->actingAs($h['admin'])->get(route('admin.pagos.proveedores.index', ['semana' => '2026-09-10', 'zona' => $h['zonaB'], 'estado' => 'todos']))
        ->assertOk()
        ->assertSee('Seleccionar pendientes de la zona')
        ->getContent();

    expect(preg_match_all('/data-pendiente="1"/', $html))->toBe(1)   // Juana
        ->and(preg_match_all('/data-pendiente="0"/', $html))->toBe(1); // Rosa (pagada)
});

// CASO 10
it('al confirmar un pago con Estado Todos la fila permanece como Pagado y la hoja lateral sigue mostrándolo', function () {
    $h = historial_pagos_proveedores();

    $this->actingAs($h['admin'])->post(route('admin.pagos.proveedores.print-and-pay'), [
        'ids' => [$h['juana']->id],
        'semana' => '2026-09-10', 'zona' => '', 'estado' => 'todos', 'buscar' => '', 'ver' => $h['juana']->id,
    ])->assertRedirect(route('admin.pagos.proveedores.index', ['semana' => '2026-09-10', 'estado' => 'todos']))
        ->assertSessionHas('ver_pago_id', $h['juana']->id);

    $respuesta = $this->get(route('admin.pagos.proveedores.index', ['semana' => '2026-09-10', 'estado' => 'todos']))->assertOk();

    expect(nombres_en_tabla($respuesta->getContent()))->toContain('Juana Quispe');
    expect($respuesta->getContent())->toMatch('/<tr data-fila data-id="'.$h['juana']->id.'" data-pendiente="0"/');
    $respuesta->assertSee('data-ver-id="'.$h['juana']->id.'"', false);

    // Con el filtro Pendientes, en cambio, ya no cumple el estado y desaparece.
    expect(pantalla_pago(['semana' => '2026-09-10', 'estado' => 'pendientes'])[1])->not->toContain('Juana Quispe');
});

it('conserva Semana Todos, zona, estado y búsqueda al volver de pagar', function () {
    $h = historial_pagos_proveedores();

    $this->actingAs($h['admin'])->post(route('admin.pagos.proveedores.print-and-pay'), [
        'ids' => [$h['luis']->id], 'semana' => 'todos', 'zona' => $h['zonaA'], 'estado' => 'pendientes', 'buscar' => 'luis',
    ])->assertRedirect(route('admin.pagos.proveedores.index', ['semana' => 'todos', 'zona' => $h['zonaA'], 'estado' => 'pendientes', 'buscar' => 'luis']));
});

// CASOS 11 y 12
it('el comprobante de un pagado sigue accesible y usa el snapshot histórico, no la configuración actual', function () {
    $h = historial_pagos_proveedores();
    $milkon = $h['milkon'];

    // El precio "cambia" después del pago: ni el comprobante ni la fila deben reflejarlo.
    $milkon->update(['monto_calculado' => 99.99, 'base_calculo' => ['litros_pagables' => 20, 'precio_base_litro' => 1.80, 'subtotal' => 99.99]]);
    $milkon->proveedor->zona->update(['nombre' => 'Zona renombrada']);

    $respuesta = $this->actingAs($h['admin'])->getJson(route('admin.pagos.proveedores.preview', ['ids' => [$milkon->id]]))->assertOk();
    $html = $respuesta->json('hojas.0.html');

    expect($html)->toContain('Milkon Mamani')->toContain('Collana 1')->toContain('S/ 1.70')->toContain('S/ 34.00')
        ->toContain('Pagado el')->toContain('por '.$h['admin']->username)
        ->not->toContain('1.80')->not->toContain('99.99')->not->toContain('Zona renombrada');

    $this->get(route('admin.pagos.proveedores.receipt', $milkon))->assertOk()->assertSee('S/ 34.00')->assertDontSee('99.99');

    $fila = $this->get(route('admin.pagos.proveedores.index', ['semana' => 'todos']))->getContent();
    expect(preg_match('/<tr data-fila data-id="'.$milkon->id.'".*?<\/tr>/s', $fila, $m))->toBe(1)
        ->and($m[0])->toContain('S/ 1.70')->toContain('S/ 34.00')->toContain('Collana 1')->not->toContain('99.99')->not->toContain('Zona renombrada');
});

// CASOS 13 y 14
it('las semanas del combo son únicas, ordenadas de la más reciente a la más antigua y Todos llega hasta la primera', function () {
    $h = historial_pagos_proveedores();

    $respuesta = $this->actingAs($h['admin'])->get(route('admin.pagos.proveedores.index', ['semana' => 'todos']))->assertOk();
    $semanas = $respuesta->viewData('datos')['semanas'];

    expect(array_column($semanas, 'etiqueta'))->toBe(['Semana 39', 'Semana 38', 'Semana 37'])
        ->and(array_column($semanas, 'inicio'))->toBe(['2026-09-17', '2026-09-10', '2026-09-03']);

    $respuesta->assertSeeInOrder(['<option value="todos" selected>Todos</option>', 'Semana 39', 'Semana 38', 'Semana 37'], false);
    preg_match('/<select id="filtro-semana".*?<\/select>/s', $respuesta->getContent(), $combo);
    expect(substr_count($combo[0], 'Semana 38'))->toBe(1)->and(substr_count($combo[0], '<option'))->toBe(4);

    // Todos incluye la primera semana con registros (Pedro Ticona, Semana 37).
    expect(pantalla_pago(['semana' => 'todos'])[1])->toContain('Pedro Ticona');
    $respuesta->assertSee('Todo el historial');
});

it('una semana que no existe cae a la más reciente con pendientes y un valor ambiguo no se toma como Todos', function () {
    $h = historial_pagos_proveedores();
    $this->actingAs($h['admin']);

    expect(pantalla_pago(['semana' => 'cualquier-cosa', 'estado' => 'todos'])[1])->toBe(['Luis Calcina']);
});

// CASO 15
it('el buscador tiene en cuenta a los proveedores pagados (nombre, zona, semana y estado)', function () {
    $h = historial_pagos_proveedores();

    $respuesta = $this->actingAs($h['admin'])->get(route('admin.pagos.proveedores.index', ['semana' => 'todos', 'buscar' => 'milkon']))->assertOk();
    $html = $respuesta->getContent();

    preg_match('/<tr data-fila data-id="'.$h['milkon']->id.'"[^>]*data-buscar-texto="([^"]*)"/', $html, $m);

    expect($m[1] ?? '')->toContain('milkon')->toContain('collana 1')->toContain('semana 38')->toContain('pagado');
    $respuesta->assertSee('value="milkon"', false);
});

it('los KPIs cuentan solo pendientes y los pagados visibles no los inflan', function () {
    $h = historial_pagos_proveedores();
    $this->actingAs($h['admin']);

    $todos = $this->get(route('admin.pagos.proveedores.index', ['semana' => 'todos', 'estado' => 'todos']))->viewData('datos')['kpis'];
    $pendientes = $this->get(route('admin.pagos.proveedores.index', ['semana' => 'todos', 'estado' => 'pendientes']))->viewData('datos')['kpis'];
    $pagados = $this->get(route('admin.pagos.proveedores.index', ['semana' => 'todos', 'estado' => 'pagados']))->viewData('datos')['kpis'];

    // Pendientes: Luis 30 L + Juana 40 L + Pedro 15 L = 85 L y S/ 51.00 + 68.00 + 25.50.
    expect($todos)->toBe(['pendientes' => 3, 'litros' => 85.0, 'totalPendiente' => 144.5])
        ->and($pendientes)->toBe($todos)
        ->and($pagados)->toBe(['pendientes' => 0, 'litros' => 0.0, 'totalPendiente' => 0.0]);
});

it('la zona se combina con Semana Todos', function () {
    $h = historial_pagos_proveedores();
    $this->actingAs($h['admin']);

    expect(pantalla_pago(['semana' => 'todos', 'zona' => (string) $h['zonaA'], 'estado' => 'todos'])[1])
        ->toEqualCanonicalizing(['Luis Calcina', 'Milkon Mamani', 'Pedro Ticona']);
});

it('un lote que incluye un pago ya pagado se rechaza completo y no paga a los demás', function () {
    $h = historial_pagos_proveedores();

    $this->actingAs($h['admin'])->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$h['luis']->id, $h['milkon']->id]])
        ->assertSessionHas('error');

    expect($h['luis']->fresh()->estado)->toBe('PENDIENTE')
        ->and(PagoModel::where('obligacion_pago_id', $h['milkon']->id)->count())->toBe(1)
        ->and(PagoModel::count())->toBe(2);
});

it('la vista previa de un pagado no cambia su estado ni crea pagos', function () {
    $h = historial_pagos_proveedores();

    $this->actingAs($h['admin'])->getJson(route('admin.pagos.proveedores.preview', ['ids' => [$h['milkon']->id, $h['luis']->id]]))
        ->assertOk()->assertJsonCount(2, 'hojas');

    expect(PagoModel::count())->toBe(2)->and($h['luis']->fresh()->estado)->toBe('PENDIENTE');
});
