<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/DashboardFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00', 'UTC'));
});

it('renderiza los seis KPI con valores reales y el usuario autenticado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = dashboard_entrega('2026-09-10 09:00:00', 30.25, 'ACEPTADA');
    dashboard_valorizacion($entrega, '60.50', '2026-09-10 10:00:00');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertSeeText(['Total acopiado', 'Proveedores atendidos', 'Calidad aceptada', 'Entregas observadas', 'Total valorizado', 'Producción']);
    $response->assertSeeText(['30.25 L', '1 / 1', '100.0%', 'S/ 60.50', 'Buenos días, '.$admin->username]);
});

it('mantiene el filtro activo y las fechas personalizadas en el formulario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['periodo' => 'personalizado', 'desde' => '2026-09-02', 'hasta' => '2026-09-10']));

    $html = new DOMDocument;
    @$html->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($html);
    expect(trim($xpath->evaluate('string(//nav[@aria-label="Período del dashboard"]/a[@aria-current="page"])')))->toBe('Personalizado');
    expect($xpath->evaluate('string(//input[@name="desde"]/@value)'))->toBe('2026-09-02');
    expect($xpath->evaluate('string(//input[@name="hasta"]/@value)'))->toBe('2026-09-10');
});

it('serializa datasets JSON numéricos y ofrece controles y tabla accesible', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    dashboard_entrega('2026-09-10 08:00:00', 32.75, 'OBSERVADA');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $html = new DOMDocument;
    @$html->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($html);
    $dias = json_decode($xpath->evaluate('string(//*[@data-admin-dashboard]/@data-litros-por-dia)'), true, flags: JSON_THROW_ON_ERROR);
    $calidad = json_decode($xpath->evaluate('string(//*[@data-admin-dashboard]/@data-distribucion-calidad)'), true, flags: JSON_THROW_ON_ERROR);
    expect($dias[0])->toMatchArray(['fecha' => '2026-09-10', 'litros' => 32.75, 'entregas' => 1]);
    expect($calidad[1])->toMatchArray(['resultado' => 'OBSERVADA', 'cantidad' => 1, 'porcentaje' => 100, 'litros' => 32.75]);
    $response->assertSee('data-chart-metric="entregas"', false)->assertSee('Consultar datos del gráfico');
});

it('muestra nombres reales de proveedores y zonas y escapa contenido peligroso', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = dashboard_entrega('2026-09-10 08:00:00');
    $entrega->proveedor->update(['nombres' => 'Ana <script>alert(1)</script>', 'apellidos' => 'Quispe']);
    $entrega->zona->update(['nombre' => 'Valle <img src=x onerror=alert(1)>']);
    dashboard_comunicado('Aviso <script>alert(2)</script>', '2026-09-10 08:00:00');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertSee('Ana <script>alert(1)</script> Quispe')->assertSee('Valle <img src=x onerror=alert(1)>');
    $response->assertSee('Aviso <script>alert(2)</script>');
    $response->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false)->assertDontSee('<script>alert(2)</script>', false);
    $response->assertDontSee('Proveedor #')->assertDontSee('Zona #');
});

it('presenta estados vacíos sin gráficos ni datos ficticios', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertSeeText(['0.00 L', '0 / 0', 'Sin datos de calidad', 'No hay entregas en este período', 'No hay zonas registradas', 'No existen lotes de producción', 'No hay actividad reciente', 'No hay comunicados publicados']);
    $response->assertDontSee('data-volume-chart', false)->assertDontSee('data-quality-chart', false);
});

it('enlaza y activa Inicio en el sidebar administrativo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $html = new DOMDocument;
    @$html->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
    $xpath = new DOMXPath($html);
    $enlace = $xpath->query('//aside//a[normalize-space(.)="Inicio"]')->item(0);
    expect($enlace)->not->toBeNull();
    expect($enlace->getAttribute('href'))->toBe(route('admin.dashboard'));
    expect($enlace->getAttribute('aria-current'))->toBe('page');
});
