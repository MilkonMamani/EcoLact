<?php

use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/../../Support/DashboardFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00', config('app.timezone')));
});

function dashboard_xpath(TestResponse $response): DOMXPath
{
    $html = new DOMDocument;
    @$html->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());

    return new DOMXPath($html);
}

it('muestra el encabezado del resumen con el rango aplicado y el filtro activo', function () {
    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard', ['periodo' => 'semana']));

    $xpath = dashboard_xpath($response);
    expect(trim($xpath->evaluate('string(//h1)')))->toBe('Resumen general');
    expect(trim($xpath->evaluate('string(//nav[@aria-label="Período del dashboard"]/a[@aria-current="page"])')))->toBe('7 días');
    $response->assertSeeText(['Esta semana', 'lun. 7 sep. – dom. 13 sep. 2026']);
    $response->assertSeeText('Lo esencial de tu operación, en un solo lugar')->assertDontSee('Personalizado');
});

it('adapta los títulos de los indicadores al período con valores reales', function (string $periodo, array $titulos) {
    dashboard_entrega('2026-09-10 08:00:00', 30.25);
    dashboard_venta('2026-09-10 09:00:00', '45.50');

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard', ['periodo' => $periodo]));

    $response->assertSeeText($titulos)->assertSeeText(['30.25', 'S/ 45.50', '1 proveedor con entregas']);
})->with([
    'hoy' => ['hoy', ['Leche acopiada hoy', 'Producción de hoy', 'Ventas del día', 'Pagos pendientes de hoy']],
    'semana' => ['semana', ['Leche acopiada esta semana', 'Producción de la semana', 'Ventas de la semana', 'Pagos pendientes de la semana']],
    'mes' => ['mes', ['Leche acopiada este mes', 'Producción del mes', 'Ventas del mes', 'Pagos pendientes del mes']],
]);

it('no presenta distribución ni porcentajes de calidad ni enlaces vacíos', function () {
    dashboard_entrega('2026-09-10 08:00:00', 30, 'ACEPTADA');

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard'));

    $response->assertDontSee('Distribución por calidad')->assertDontSee('Calidad aceptada')->assertDontSee('data-quality-chart', false);
    expect(dashboard_xpath($response)->query('//a[@href="#" or not(@href) or @href=""]')->length)->toBe(0);
});

it('separa los pagos de proveedores y trabajadores con destinos filtrados según su calendario', function () {
    dashboard_obligacion_proveedor('2026-08-27', '10.00');
    dashboard_obligacion_trabajador('2026-08', '2026-09-01', '1200.00');

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard', ['periodo' => 'mes']));

    $xpath = dashboard_xpath($response);
    expect($xpath->evaluate('string(//a[normalize-space(.)="Ver pagos a proveedores"]/@href)'))
        ->toBe(route('admin.pagos.proveedores.index', ['semana' => 'todos', 'estado' => 'pendientes']));
    expect($xpath->evaluate('string(//a[normalize-space(.)="Ver pagos a trabajadores"]/@href)'))
        ->toBe(route('admin.pagos.trabajadores.index', ['periodo' => '2026-08', 'estado' => 'PENDIENTE']));
    $response->assertSeeText(['Proveedores · semanal', 'Trabajadores · mensual', 'S/ 10.00', 'S/ 1,200.00', 'S/ 1,210.00']);
});

it('enlaza cada aviso por atender a su pantalla con el filtro correspondiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = dashboard_entrega('2026-09-10 08:00:00');
    AnalisisCalidadModel::create(['entrega_id' => $entrega->id, 'estado' => 'BORRADOR', 'fecha_analisis' => '2026-09-10 08:00:00']);
    fixture_insumo('Cuajo', 'L', stock: 1, minimo: 5, userId: $admin->id);
    dashboard_lote('L-1', '2026-09-10 07:00:00');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $xpath = dashboard_xpath($response);
    $enlaces = fn (string $texto): array => collect(iterator_to_array($xpath->query('//*[@aria-labelledby="dash-atender-titulo"]//a[normalize-space(.)="'.$texto.'"]')))
        ->map(fn (DOMElement $a): string => $a->getAttribute('href'))->all();
    $rango = ['desde' => '2026-09-10', 'hasta' => '2026-09-10'];
    expect($enlaces('Revisar calidad'))->toBe([route('admin.analisis-calidad.index', ['estado' => 'BORRADOR', 'fecha' => 'personalizado'] + $rango)]);
    expect($enlaces('Ver insumos'))->toContain(route('admin.insumos.index', ['nivel' => 'bajo']));
    expect($enlaces('Ver producción'))->toBe([route('admin.produccion.index', ['estado' => 'EN_PROCESO', 'periodo' => 'personalizado'] + $rango)]);
    $response->assertDontSee('Ver inventario');
});

it('identifica las existencias como saldo actual con enlaces a insumos y productos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(150, $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['periodo' => 'mes']));

    $response->assertSeeText(['Inventario actual', 'Saldo a este momento, no depende del período', 'Leche disponible', '150 L']);
    $xpath = dashboard_xpath($response);
    expect($xpath->evaluate('string(//*[@aria-labelledby="dash-inventario-titulo"]//a[normalize-space(.)="Ver productos"]/@href)'))
        ->toBe(route('admin.productos.index', ['estado' => 'DISPONIBLE']));
});

it('presenta estados vacíos compactos y el gráfico solo con ceros reales', function () {
    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard'));

    $response->assertSeeText(['Sin entregas en este período.', 'Sin acopio en este período.', 'No se iniciaron lotes en este período.', 'No hay personal registrado para este día.', 'No se publicaron comunicados en este período.', 'S/ 0.00']);
    $response->assertSee('data-acopio-chart', false)->assertDontSee('Consultar datos del gráfico');
    $intervalos = json_decode(dashboard_xpath($response)->evaluate('string(//*[@data-admin-dashboard]/@data-acopio)'), true, flags: JSON_THROW_ON_ERROR);
    expect(collect($intervalos)->pluck('litros')->filter()->all())->toBe([]);
});

it('serializa los intervalos del gráfico y ofrece la tabla de datos', function () {
    dashboard_entrega('2026-09-10 08:15:00', 32.75);

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard'));

    $intervalos = json_decode(dashboard_xpath($response)->evaluate('string(//*[@data-admin-dashboard]/@data-acopio)'), true, flags: JSON_THROW_ON_ERROR);
    expect($intervalos)->toHaveCount(24);
    expect($intervalos[8])->toMatchArray(['clave' => '08', 'litros' => 32.75, 'entregas' => 1, 'futuro' => false]);
    $response->assertSee('data-acopio-chart', false)->assertSeeText('Consultar datos del gráfico');
});

it('muestra la asistencia de hoy aunque el período elegido sea el mes', function () {
    $presente = dashboard_trabajador();
    dashboard_trabajador();
    dashboard_jornada($presente->id, '2026-09-10');
    dashboard_jornada($presente->id, '2026-09-08');

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard', ['periodo' => 'mes']));

    $response->assertSeeTextInOrder(['Asistencia de hoy', '1 de 2 presentes', '0 faltas · 1 sin marcar']);
    expect(dashboard_xpath($response)->evaluate('string(//*[@aria-labelledby="dash-asistencia-titulo"]//a[normalize-space(.)="Ver asistencia"]/@href)'))
        ->toBe(route('admin.asistencia.index', ['fecha' => '2026-09-10']));
});

it('escapa nombres de zonas y comunicados', function () {
    dashboard_entrega('2026-09-10 08:00:00')->zona->update(['nombre' => 'Valle <img src=x onerror=alert(1)>']);
    dashboard_comunicado('Aviso <script>alert(2)</script>', '2026-09-10 08:00:00');

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard'));

    $response->assertSee('Valle <img src=x onerror=alert(1)>')->assertSee('Aviso <script>alert(2)</script>');
    $response->assertDontSee('<img src=x onerror=alert(1)>', false)->assertDontSee('<script>alert(2)</script>', false);
});

it('enlaza y activa Inicio en el sidebar administrativo', function () {
    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.dashboard'));

    $enlace = dashboard_xpath($response)->query('//aside//a[normalize-space(.)="Inicio"]')->item(0);
    expect($enlace)->not->toBeNull();
    expect($enlace->getAttribute('href'))->toBe(route('admin.dashboard'));
    expect($enlace->getAttribute('aria-current'))->toBe('page');
});
