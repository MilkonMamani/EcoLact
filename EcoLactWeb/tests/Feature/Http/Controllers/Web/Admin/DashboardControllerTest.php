<?php

use App\Application\Dashboard\DashboardAdministradorData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:00:00', 'UTC'));
});

it('permite al administrador obtener el dashboard real con hoy por defecto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()->assertViewIs('admin.dashboard')
        ->assertViewHas('dashboard', fn ($data) => $data instanceof DashboardAdministradorData && $data->periodo->nombre === 'hoy');
    expect(Route::getRoutes()->getByName('admin.dashboard')->gatherMiddleware())->toContain('auth', 'active', 'role:ADMINISTRADOR');
});

it('rechaza al acopiador con 403', function () {
    $acopiador = fixture_acopiador(fixture_zona());

    $this->actingAs($acopiador->user)->get(route('admin.dashboard'))->assertForbidden();
});

it('rechaza al proveedor con 403', function () {
    $proveedor = fixture_proveedor(fixture_zona());

    $this->actingAs($proveedor->user)->get(route('admin.dashboard'))->assertForbidden();
});

it('rechaza al administrador inactivo con 403', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $admin->update(['estado' => 'INACTIVO']);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
});

it('rechaza invitados con 401 JSON aunque exista login Web', function () {
    $this->getJson(route('admin.dashboard'))->assertUnauthorized();

    expect(Route::has('login'))->toBeTrue();
});

it('acepta filtros temporales válidos y entrega sus límites inclusivos', function (array $filtro, string $desde, string $hasta) {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.dashboard', $filtro));

    $response->assertOk()->assertViewHas('dashboard', fn ($data) => $data->periodo->nombre === $filtro['periodo']
        && $data->periodo->desde->format('Y-m-d H:i:s') === $desde.' 00:00:00'
        && $data->periodo->hasta->format('Y-m-d H:i:s') === $hasta.' 23:59:59');
})->with([
    'hoy' => [['periodo' => 'hoy'], '2026-09-10', '2026-09-10'],
    'semana calendario' => [['periodo' => 'semana'], '2026-09-07', '2026-09-13'],
    'mes' => [['periodo' => 'mes'], '2026-09-01', '2026-09-30'],
    'personalizado' => [['periodo' => 'personalizado', 'desde' => '2026-08-25', 'hasta' => '2026-09-02'], '2026-08-25', '2026-09-02'],
    'mismo día' => [['periodo' => 'personalizado', 'desde' => '2026-09-01', 'hasta' => '2026-09-01'], '2026-09-01', '2026-09-01'],
]);

it('devuelve errores de validación en filtros inválidos', function (array $filtro, array $campos) {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->getJson(route('admin.dashboard', $filtro))
        ->assertUnprocessable()->assertJsonValidationErrors($campos);
})->with([
    'período desconocido' => [['periodo' => 'anual'], ['periodo']],
    'período vacío' => [['periodo' => ''], ['periodo']],
    'período como array' => [['periodo' => ['hoy']], ['periodo']],
    'fechas ausentes' => [['periodo' => 'personalizado'], ['desde', 'hasta']],
    'desde ausente' => [['periodo' => 'personalizado', 'hasta' => '2026-09-10'], ['desde']],
    'hasta ausente' => [['periodo' => 'personalizado', 'desde' => '2026-09-10'], ['hasta']],
    'rango invertido' => [['periodo' => 'personalizado', 'desde' => '2026-09-11', 'hasta' => '2026-09-10'], ['hasta']],
    'día inexistente' => [['periodo' => 'personalizado', 'desde' => '2026-02-30', 'hasta' => '2026-09-10'], ['desde']],
    'formato incorrecto' => [['periodo' => 'personalizado', 'desde' => '10/09/2026', 'hasta' => '11/09/2026'], ['desde', 'hasta']],
]);

it('retorna el error en sesión para un formulario HTML inválido', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->from(route('admin.dashboard'))->get(route('admin.dashboard', [
        'periodo' => 'personalizado', 'desde' => '2026-09-11', 'hasta' => '2026-09-10',
    ]))->assertRedirect(route('admin.dashboard'))->assertSessionHasErrors([
        'hasta' => 'La fecha hasta debe ser igual o posterior a desde.',
    ]);
});

it('ignora fechas externas al filtro personalizado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.dashboard', ['periodo' => 'hoy', 'desde' => 'incorrecto', 'hasta' => 'incorrecto']))
        ->assertOk()->assertViewHas('dashboard', fn ($data) => $data->periodo->desde->toDateString() === '2026-09-10');
});

it('redirige invitados desde la raíz al login', function () {
    $this->get('/')->assertRedirectToRoute('login');
});
