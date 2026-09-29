<?php

use App\Application\Ruta\RegistrarRuta;
use App\Domain\Ruta\Ruta;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/** @return array{0: ZonaModel, 1: Ruta} */
function zona_con_ruta(string $zona, string $ruta): array
{
    $modelo = fixture_zona($zona);

    return [$modelo, app(RegistrarRuta::class)->execute(zonaId: $modelo->id, nombre: $ruta)];
}

function proveedor_nombrado(ZonaModel $zona, string $dni, string $estado = 'ACTIVO'): ProveedorModel
{
    $proveedor = fixture_proveedor($zona, $dni);
    $proveedor->update(['nombres' => 'Rosa', 'apellidos' => 'Quispe', 'estado' => $estado]);

    return $proveedor;
}

it('el listado ya no muestra la columna # y el estado es un switch en lugar de un badge', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$zona] = zona_con_ruta('Collana 1', 'Ruta 1');
    $activo = proveedor_nombrado($zona, '71000001');
    $inactivo = proveedor_nombrado($zona, '71000002', 'INACTIVO');

    $html = $this->actingAs($admin)->get(route('admin.proveedores.index'))
        ->assertOk()
        ->assertSeeInOrder(['Nombre', 'DNI', 'Teléfono', 'Zona', 'Última entrega', 'Total (L)', 'Estado'])
        ->assertSee('data-url-desactivar="'.route('admin.proveedores.desactivar', $activo->id).'"', false)
        ->assertSee('data-url-reactivar="'.route('admin.proveedores.reactivar', $inactivo->id).'"', false)
        ->getContent();

    $tabla = substr($html, strpos($html, '<table>'), strpos($html, '</table>') - strpos($html, '<table>'));
    expect($tabla)->not->toContain('>#</th>')->not->toContain('class="is-id"')->not->toContain('ring-inset');
    expect(preg_match_all('/role="switch" aria-checked="true"/', $tabla))->toBe(1)
        ->and(preg_match_all('/role="switch" aria-checked="false"/', $tabla))->toBe(1);
});

it('el modal de reactivación lista zonas activas y sus rutas activas desde la BD', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$collana, $rutaCollana] = zona_con_ruta('Collana 1', 'Ruta Norte');
    zona_con_ruta('Zona Cerrada', 'Ruta Cerrada')[0]->update(['estado' => 'INACTIVO']);
    proveedor_nombrado($collana, '71000003', 'INACTIVO');

    $html = $this->actingAs($admin)->get(route('admin.proveedores.index'))->getContent();
    $modal = substr($html, strpos($html, 'id="modal-reactivar-proveedor"'));
    $modal = substr($modal, 0, strpos($modal, '</form>'));

    expect($modal)->toContain('Selecciona la zona a la que se reincorpora')
        ->toContain('Collana 1')
        ->toContain('<option value="'.$rutaCollana->id().'" data-padre="'.$collana->id.'">Ruta Norte</option>')
        ->not->toContain('Zona Cerrada')
        ->not->toContain('Ruta Cerrada');
});

it('desactivar conserva el registro y su asignación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$zona] = zona_con_ruta('Collana 1', 'Ruta 1');
    $proveedor = proveedor_nombrado($zona, '71000004');

    $this->actingAs($admin)
        ->patchJson(route('admin.proveedores.desactivar', $proveedor->id))
        ->assertOk()
        ->assertJsonPath('proveedor.estado', 'INACTIVO');

    expect($proveedor->fresh())->estado->toBe('INACTIVO')->zona_id->toBe($zona->id);
});

it('no reactiva sin zona ni con una ruta de otra zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$collana] = zona_con_ruta('Collana 1', 'Ruta Collana');
    [$faon, $rutaFaon] = zona_con_ruta('Faon', 'Ruta Faon');
    $proveedor = proveedor_nombrado($collana, '71000005', 'INACTIVO');
    $this->actingAs($admin);

    $this->patchJson(route('admin.proveedores.reactivar', $proveedor->id), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['zona_id' => 'Selecciona la zona a la que se reincorpora.']);

    $this->patchJson(route('admin.proveedores.reactivar', $proveedor->id), ['zona_id' => $collana->id, 'ruta_id' => $rutaFaon->id()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ruta_id');

    expect($proveedor->fresh()->estado)->toBe('INACTIVO');
});

it('al elegir zona y ruta se reactiva, queda en esa zona y la fila y el panel lo reflejan', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$collana] = zona_con_ruta('Collana 1', 'Ruta Collana');
    [$faon, $rutaFaon] = zona_con_ruta('Faon', 'Ruta Faon');
    $proveedor = proveedor_nombrado($collana, '71000006', 'INACTIVO');

    $this->actingAs($admin)
        ->patchJson(route('admin.proveedores.reactivar', $proveedor->id), ['zona_id' => $faon->id, 'ruta_id' => $rutaFaon->id()])
        ->assertOk()
        ->assertJson(['proveedor' => ['id' => $proveedor->id, 'estado' => 'ACTIVO', 'zonaId' => $faon->id, 'zonaNombre' => 'Faon']]);

    expect($proveedor->fresh())->estado->toBe('ACTIVO')->zona_id->toBe($faon->id);
    expect(RutaDetalleModel::where('proveedor_id', $proveedor->id)->value('ruta_id'))->toBe($rutaFaon->id());

    $this->get(route('admin.proveedores.index', ['proveedor' => $proveedor->id]))
        ->assertSee('data-fila-zona>Faon', false)
        ->assertSeeInOrder(['Activo', 'Rosa Quispe', 'Zona asignada', 'Faon', 'Ruta de acopio', 'Ruta Faon']);
});

it('solo un administrador puede desactivar o reactivar', function () {
    [$zona, $ruta] = zona_con_ruta('Collana 1', 'Ruta 1');
    $proveedor = proveedor_nombrado($zona, '71000007');
    $otro = fixture_proveedor($zona);

    $this->actingAs($otro->user)->patchJson(route('admin.proveedores.desactivar', $proveedor->id))->assertForbidden();
    $this->actingAs($otro->user)->patchJson(route('admin.proveedores.reactivar', $proveedor->id), ['zona_id' => $zona->id, 'ruta_id' => $ruta->id()])->assertForbidden();

    expect($proveedor->fresh()->estado)->toBe('ACTIVO');
});

it('el QR sigue solo en Resumen, centrado y con el token real', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$zona] = zona_con_ruta('Collana 1', 'Ruta 1');
    $proveedor = proveedor_nombrado($zona, '71000008');

    $html = $this->actingAs($admin)->get(route('admin.proveedores.panel', $proveedor->id))->assertOk()->getContent();

    expect(substr_count($html, 'data-qr-code='))->toBe(1)
        ->and($html)->toContain('<div class="fichas-qr">')
        ->toContain('data-qr-code="'.base64_encode($proveedor->qr_token).'"')
        ->and(strpos($html, 'data-qr-code='))->toBeLessThan(strpos($html, 'data-fichas-tab-panel="entregas"'));
});
