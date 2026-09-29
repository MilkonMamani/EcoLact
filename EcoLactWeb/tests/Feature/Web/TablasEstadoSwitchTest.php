<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/** Contenido de la <table> principal del listado (sin el panel derecho). */
function tabla_listado(string $html): string
{
    $inicio = strpos($html, '<table>');

    return substr($html, $inicio, strpos($html, '</table>', $inicio) - $inicio);
}

it('Acopiadores y Proveedores no tienen columna # y empiezan por Nombre', function (string $ruta) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    fixture_acopiador($zona, '44440001');
    fixture_proveedor($zona, '55550001');

    $tabla = tabla_listado($this->actingAs($admin)->get(route($ruta))->assertOk()->getContent());

    expect($tabla)->not->toContain('>#</th>')->not->toContain('class="is-id"');
    expect(strip_tags(substr($tabla, 0, strpos($tabla, '</th>'))))->toContain('Nombre');
})->with(['admin.acopiadores.index', 'admin.proveedores.index']);

it('el estado de la tabla es solo el switch, con title y aria-label accesibles', function (string $ruta, string $entidad) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $acopiador = fixture_acopiador($zona, '44440002');
    $acopiador->update(['nombres' => 'Luis', 'apellidos' => 'Mamani', 'estado' => 'INACTIVO']);
    $proveedor = fixture_proveedor($zona, '55550002');
    $proveedor->update(['nombres' => 'Luis', 'apellidos' => 'Mamani', 'estado' => 'INACTIVO']);

    $tabla = tabla_listado($this->actingAs($admin)->get(route($ruta))->getContent());
    $switch = substr($tabla, strpos($tabla, '<button type="button" role="switch"'));
    $switch = substr($switch, 0, strpos($switch, '</button>') + 9);

    expect($switch)
        ->toContain('aria-checked="false"')
        ->toContain('title="Inactivo"')
        ->toContain('aria-label="'.$entidad.' inactivo: Luis Mamani"')
        ->and(trim(strip_tags($switch)))->toBe('');
    expect($tabla)->toContain('<td class="is-estado">');
})->with([
    ['admin.acopiadores.index', 'Acopiador'],
    ['admin.proveedores.index', 'Proveedor'],
]);

it('el panel derecho conserva el estado en texto', function (string $ruta, string $modelo) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $registro = $modelo === 'acopiador' ? fixture_acopiador($zona, '44440003') : fixture_proveedor($zona, '55550003');

    $this->actingAs($admin)->get(route($ruta, $registro->id))
        ->assertOk()
        ->assertSeeInOrder(['fichas-panel-head', 'Activo']);
})->with([
    ['admin.acopiadores.panel', 'acopiador'],
    ['admin.proveedores.panel', 'proveedor'],
]);
