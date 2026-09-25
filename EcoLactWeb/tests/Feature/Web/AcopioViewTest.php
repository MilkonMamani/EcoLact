<?php

use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla principal de acopio filtra por zona y ya no muestra KPIs', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA, '11112222');
    $proveedorB = fixture_proveedor($zonaB, '33334444');
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);
    fixture_entrega($proveedorA, $acopiadorA, 30.0);
    fixture_entrega($proveedorB, $acopiadorB, 20.0);

    $response = $this->actingAs($admin)->get(route('admin.entregas.index', ['zona_id' => $zonaA->id]));

    $response->assertOk();
    $response->assertSee('11112222')->assertDontSee('33334444');
    $response->assertDontSee('Litros registrados')->assertDontSee('Pendientes de análisis');
    $response->assertSeeText(['Acopio', 'Registro y seguimiento de entregas de leche.', 'Mostrando 1 a 1 de 1 entregas']);
});

/**
 * Crea entregas en bloque (una consulta) para probar la paginación sin cientos de INSERT.
 */
function acopio_entregas_en_bloque(int $cantidad): void
{
    $zona = fixture_zona('Collana 1');
    $proveedor = fixture_proveedor($zona, '77777777');
    $acopiador = fixture_acopiador($zona);
    $inicio = new DateTimeImmutable('2026-09-01 06:00:00');

    EntregaModel::insert(array_map(fn (int $i): array => [
        'proveedor_id' => $proveedor->id,
        'acopiador_id' => $acopiador->id,
        'zona_id' => $zona->id,
        'registrado_por_user_id' => $acopiador->user_id,
        'fecha_hora' => $inicio->modify("+{$i} minutes")->format('Y-m-d H:i:s'),
        'litros' => 10 + $i,
        'metodo_identificacion' => 'DNI',
        'estado' => 'PENDIENTE_ANALISIS',
        'created_at' => now(),
        'updated_at' => now(),
    ], range(1, $cantidad)));
}

it('pagina el acopio en el servidor de 100 en 100 con el total real', function (int $cantidad, int $pagina, string $texto, int $filas) {
    acopio_entregas_en_bloque($cantidad);

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.entregas.index', ['pagina' => $pagina]));

    $response->assertOk()->assertSeeText($texto);
    expect(substr_count($response->getContent(), '<tr data-acopio-fila>'))->toBe($filas);
})->with([
    'menos de 100' => [3, 1, 'Mostrando 1 a 3 de 3 entregas', 3],
    'exactamente 100' => [100, 1, 'Mostrando 1 a 100 de 100 entregas', 100],
    'más de 100, segunda página' => [101, 2, 'Mostrando 101 a 101 de 101 entregas', 1],
    'más de 300, primera página' => [347, 1, 'Mostrando 1 a 100 de 347 entregas', 100],
    'más de 300, segunda página' => [347, 2, 'Mostrando 101 a 200 de 347 entregas', 100],
    'más de 300, última página' => [347, 4, 'Mostrando 301 a 347 de 347 entregas', 47],
]);

it('los enlaces de paginación conservan búsqueda y filtros', function () {
    acopio_entregas_en_bloque(250);
    $filtros = ['q' => '77777777', 'estado' => 'PENDIENTE_ANALISIS', 'fecha' => 'este_mes'];

    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.entregas.index', $filtros));

    $response->assertSeeText('Mostrando 1 a 100 de 250 entregas');
    $response->assertSee(route('admin.entregas.index', $filtros + ['pagina' => 2]));
    $response->assertSee(route('admin.entregas.index', $filtros + ['pagina' => 3]));
});

it('muestra un estado vacío compacto sin tabla cuando no hay entregas', function () {
    $response = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.entregas.index'));

    $response->assertOk()->assertSeeText('No hay entregas registradas')->assertDontSee('data-acopio-tabla', false);

    $filtrada = $this->actingAs(fixture_usuario('ADMINISTRADOR'))->get(route('admin.entregas.index', ['q' => 'nadie']));
    $filtrada->assertSeeText('Ninguna entrega coincide con los filtros');
});

it('el detalle de una entrega PENDIENTE_ANALISIS permite editar, y el formulario actualiza los datos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $get = $this->actingAs($admin)->get(route('admin.entregas.show', $entrega->id));
    $get->assertOk();
    $get->assertSee('Editar');
    $get->assertSee('Registrado por');

    $update = $this->actingAs($admin)->patch(route('admin.entregas.update', $entrega->id), [
        'litros' => 45.0,
        'motivo_modificacion' => 'Corrección de litros',
    ]);

    $update->assertRedirect(route('admin.entregas.show', $entrega->id));
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'litros' => 45.0]);
});

it('no se puede editar una entrega ya ANALIZADA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $entrega->update(['estado' => 'ANALIZADA']);

    $get = $this->actingAs($admin)->get(route('admin.entregas.show', $entrega->id));
    $get->assertOk();
    $get->assertDontSee('Editar entrega', false);

    $update = $this->actingAs($admin)->patch(route('admin.entregas.update', $entrega->id), [
        'litros' => 99.0,
        'motivo_modificacion' => 'x',
    ]);

    // Una entrega ya analizada no se edita: el error de negocio vuelve al formulario con un mensaje.
    $update->assertRedirect();
    $update->assertSessionHas('error');
});
