<?php

use App\Infrastructure\Persistence\Models\AnalisisCalidadDetalleModel;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Dos entregas analizadas por distintos usuarios y una pendiente: David (con
 * ficha de analista) confirma la de Milkon; el administrador, sin ficha,
 * confirma la de Rosa.
 *
 * @return array{admin: User, analista: User, entregas: array<string, EntregaModel>}
 */
function calidad_escenario(): array
{
    $admin = fixture_usuario('ADMINISTRADOR', 'admin');
    $analista = fixture_usuario('CALIDAD', 'dlopez');
    AnalistaCalidadModel::create(['user_id' => $analista->id, 'dni' => '40404040', 'nombres' => 'David', 'apellidos' => 'Lopez', 'estado' => 'ACTIVO']);

    $zona = fixture_zona('Collana 1');
    $acopiador = fixture_acopiador($zona);
    $proveedor = function (string $dni, string $nombres, string $apellidos) use ($zona): ProveedorModel {
        $proveedor = fixture_proveedor($zona, $dni);
        $proveedor->update(['nombres' => $nombres, 'apellidos' => $apellidos]);

        return $proveedor;
    };

    $entregas = [
        'milkon' => fixture_entrega($proveedor('77777777', 'Milkon', 'Mamani'), $acopiador, 45.0, new DateTimeImmutable('2026-09-29 06:42')),
        'rosa' => fixture_entrega($proveedor('71000001', 'Rosa', 'Quispe'), $acopiador, 30.0, new DateTimeImmutable('2026-09-28 07:10')),
        'ana' => fixture_entrega($proveedor('71000002', 'Ana', 'Condori'), $acopiador, 20.0, new DateTimeImmutable('2026-09-27 06:00')),
    ];

    fixture_analisis_confirmado($entregas['milkon'], 'ACEPTADA', $analista->id);
    fixture_analisis_confirmado($entregas['rosa'], 'RECHAZADA', $admin->id);

    return ['admin' => $admin, 'analista' => $analista, 'entregas' => $entregas];
}

it('muestra al analista real que confirmó cada análisis', function () {
    ['admin' => $admin] = calidad_escenario();

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index'))
        ->assertOk()
        ->assertSeeInOrder(['Milkon Mamani', '#', '45.00 L', 'Aceptada', 'Análisis confirmado', 'David Lopez'])
        ->assertSeeInOrder(['Rosa Quispe', 'Rechazada', 'Análisis confirmado', 'admin'])
        ->assertSeeInOrder(['Ana Condori', 'Pendiente de análisis']);
});

it('filtra por analista usando quien confirmó el análisis', function () {
    ['admin' => $admin, 'analista' => $analista] = calidad_escenario();

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index', ['analista_id' => $analista->id]))
        ->assertOk()
        ->assertSee('Milkon Mamani')
        ->assertDontSee('Rosa Quispe')
        ->assertDontSee('Ana Condori');
});

it('busca por código de entrega escrito con #', function () {
    ['admin' => $admin, 'entregas' => $entregas] = calidad_escenario();

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index', ['q' => '#'.$entregas['rosa']->id]))
        ->assertOk()
        ->assertSee('Rosa Quispe')
        ->assertDontSee('Milkon Mamani');
});

it('ordena por proveedor y por resultado en el servidor', function () {
    ['admin' => $admin] = calidad_escenario();

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index', ['orden' => 'proveedor', 'dir' => 'asc']))
        ->assertOk()
        ->assertSeeInOrder(['Ana Condori', 'Milkon Mamani', 'Rosa Quispe']);

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index', ['orden' => 'resultado', 'dir' => 'asc']))
        ->assertOk()
        ->assertSeeInOrder(['Milkon Mamani', 'Rosa Quispe', 'Ana Condori']);
});

it('pagina de 25 en 25 por defecto y acepta solo 25, 50 o 100 por página', function () {
    ['admin' => $admin] = calidad_escenario();
    $zona = fixture_zona('Collana 2');
    $acopiador = fixture_acopiador($zona);
    $proveedor = fixture_proveedor($zona);

    for ($i = 0; $i < 27; $i++) {
        fixture_entrega($proveedor, $acopiador, 10.0, new DateTimeImmutable('2026-09-01 06:00'));
    }

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index'))->assertSeeText('Mostrando 1 a 25 de 30 análisis');
    $this->actingAs($admin)->get(route('admin.analisis-calidad.index', ['por_pagina' => 50]))->assertSeeText('Mostrando 1 a 30 de 30 análisis');
    $this->actingAs($admin)->get(route('admin.analisis-calidad.index', ['por_pagina' => 7]))->assertSeeText('Mostrando 1 a 25 de 30 análisis');
    $this->actingAs($admin)->get(route('admin.analisis-calidad.index', ['pagina' => 2]))->assertSeeText('Mostrando 26 a 30 de 30 análisis');
});

it('el detalle de un análisis confirmado muestra sus parámetros registrados, resultado, analista y observación', function () {
    ['admin' => $admin, 'entregas' => $entregas] = calidad_escenario();
    $analisis = $entregas['milkon']->analisisCalidad;
    $analisis->update(['observacion' => 'Muestra tomada en porongo 2.']);
    $grasa = fixture_parametro('GRASA');
    $crioscopia = fixture_parametro('CRIOSCOPIA');
    $crioscopia->update(['nombre' => 'Punto crioscópico', 'unidad' => '°H']);

    AnalisisCalidadDetalleModel::create(['analisis_id' => $analisis->id, 'parametro_id' => $grasa->id, 'valor' => 3.6, 'origen' => 'MANUAL', 'cumple' => true]);
    AnalisisCalidadDetalleModel::create(['analisis_id' => $analisis->id, 'parametro_id' => $crioscopia->id, 'valor' => -0.512, 'origen' => 'MANUAL', 'cumple' => false]);

    $this->actingAs($admin)->get(route('admin.entregas.analisis-calidad.detalle', $entregas['milkon']))
        ->assertOk()
        ->assertSee('Análisis #A-'.str_pad((string) $analisis->id, 6, '0', STR_PAD_LEFT))
        ->assertSeeInOrder(['Milkon Mamani', '77777777', 'Collana 1', '45.00 L', 'David Lopez'])
        ->assertSeeInOrder(['GRASA', '3.6', 'Punto crioscópico', '-0.512', '°H'])
        ->assertSee('Aceptada')
        ->assertSee('Muestra tomada en porongo 2.')
        ->assertDontSee('Registrar análisis');
});

it('el detalle de una entrega sin análisis indica Pendiente y ofrece registrarlo', function () {
    ['admin' => $admin, 'entregas' => $entregas] = calidad_escenario();

    $this->actingAs($admin)->get(route('admin.entregas.analisis-calidad.detalle', $entregas['ana']))
        ->assertOk()
        ->assertSee('Pendiente de análisis')
        ->assertSee('Registrar análisis')
        ->assertSee(route('admin.entregas.analisis-calidad.index', $entregas['ana']), false);
});

it('solo el administrador puede abrir el detalle desde el listado', function () {
    ['analista' => $analista, 'entregas' => $entregas] = calidad_escenario();

    $this->actingAs($analista)->get(route('admin.entregas.analisis-calidad.detalle', $entregas['milkon']))->assertForbidden();
});
