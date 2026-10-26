<?php

use App\Application\AnalistaCalidad\RegistrarAnalistaCalidad;
use App\Application\EncargadoPlanta\RegistrarEncargadoPlanta;
use App\Application\PersonalVentas\RegistrarPersonalVentas;
use App\Application\Ventas\RegistrarComprador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

/**
 * Estos 5 enlaces del sidebar apuntaban a vistas Blade que nunca se habían
 * creado (solo existían stubs de test): en un navegador real habrían dado
 * un error 500 "view not found". Este archivo prueba, vía HTTP real (sin
 * registrar_vistas_stub), que las vistas reales existen y renderizan.
 */
uses(TestCase::class, RefreshDatabase::class);

it('admin.insumos.index e admin.insumos.show renderizan de verdad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $insumo = fixture_insumo('Cuajo', 'ml', 250.0, 50.0);

    $this->actingAs($admin)->get(route('admin.insumos.index'))->assertOk()->assertSee('Cuajo');
    $this->actingAs($admin)->get(route('admin.insumos.show', $insumo->id()))->assertOk()->assertSee('Cuajo');
});

it('admin.compradores.index renderiza de verdad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    app(RegistrarComprador::class)->execute('Comercial X');

    $this->actingAs($admin)->get(route('admin.compradores.index'))->assertOk()->assertSee('Comercial X');
});

it('admin.encargados-planta.index e .show renderizan de verdad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_rol('ENCARGADO_PLANTA');
    $encargado = app(RegistrarEncargadoPlanta::class)->execute(
        dni: '11112222',
        nombres: 'Marco',
        apellidos: 'Flores',
        telefono: null,
        username: 'marco.flores',
        password: 'Secreto#123',
    );

    $this->actingAs($admin)->get(route('admin.encargados-planta.index'))->assertOk()->assertSee('Marco Flores');
    $this->actingAs($admin)->get(route('admin.encargados-planta.show', $encargado->id()))->assertRedirect(route('admin.encargados-planta.index', ['encargado' => $encargado->id()]));
    $this->actingAs($admin)->get(route('admin.encargados-planta.panel', $encargado->id()))->assertOk()->assertSee('Marco Flores');
});

it('admin.personal-ventas.index e .show renderizan de verdad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_rol('VENTAS');
    $personal = app(RegistrarPersonalVentas::class)->execute(
        dni: '33334444',
        nombres: 'Rosa',
        apellidos: 'Choque',
        telefono: null,
        username: 'rosa.choque',
        password: 'Secreto#123',
    );

    $this->actingAs($admin)->get(route('admin.personal-ventas.index'))->assertOk()->assertSee('Rosa Choque');
    $this->actingAs($admin)->get(route('admin.personal-ventas.show', $personal->id()))->assertRedirect(route('admin.personal-ventas.index', ['personal' => $personal->id()]));
    $this->actingAs($admin)->get(route('admin.personal-ventas.panel', $personal->id()))->assertOk()->assertSee('Rosa Choque');
});

it('admin.analistas-calidad.index e .show renderizan de verdad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_rol('CALIDAD');
    $analista = app(RegistrarAnalistaCalidad::class)->execute(
        dni: '55556666',
        nombres: 'Ana',
        apellidos: 'Vargas',
        telefono: null,
        username: 'ana.vargas',
        password: 'Secreto#123',
    );

    $this->actingAs($admin)->get(route('admin.analistas-calidad.index'))->assertOk()->assertSee('Ana Vargas');
    $this->actingAs($admin)->get(route('admin.analistas-calidad.show', $analista->id()))->assertRedirect(route('admin.analistas-calidad.index', ['analista' => $analista->id()]));
    $this->actingAs($admin)->get(route('admin.analistas-calidad.panel', $analista->id()))->assertOk()->assertSee('Ana Vargas');
});
