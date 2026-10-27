<?php

use App\Infrastructure\Persistence\Models\CompradorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

it('la agenda de compradores registra uno nuevo sin pedir Público/Mayorista', function () {
    $ventasUser = fixture_usuario('VENTAS');

    $response = $this->actingAs($ventasUser)->post(route('ventas.compradores.store'), [
        'nombre' => 'Comercial Andina',
        'documento' => '20456789123',
        'telefono' => '987654321',
    ]);

    $response->assertRedirect(route('ventas.compradores.index'));
    expect(CompradorModel::sole()->tipo_cliente)->toBeNull();

    $index = $this->actingAs($ventasUser)->get(route('ventas.compradores.index'));
    $index->assertOk();
    $index->assertSee('Comercial Andina');
    $index->assertSee('20456789123');
    $index->assertDontSee('Mayoristas');
    $index->assertDontSee('Tipo de cliente');
});

it('busca compradores por nombre o documento', function () {
    $ventasUser = fixture_usuario('VENTAS');
    $this->actingAs($ventasUser)->post(route('ventas.compradores.store'), ['nombre' => 'Bodega Central', 'documento' => '20111111111']);
    $this->actingAs($ventasUser)->post(route('ventas.compradores.store'), ['nombre' => 'Minimarket Sur']);

    $response = $this->actingAs($ventasUser)->get(route('ventas.compradores.index', ['buscar' => '2011111']));

    $response->assertOk();
    $response->assertSee('Bodega Central');
    $response->assertDontSee('Minimarket Sur');
});

it('desde Nueva venta el modal guarda el comprador por fetch y devuelve sus datos para seleccionarlo', function () {
    $ventasUser = fixture_usuario('VENTAS');

    $response = $this->actingAs($ventasUser)->postJson(route('ventas.compradores.store'), ['nombre' => 'Bodega Central', 'documento' => '20123456789']);

    $response->assertCreated();
    $response->assertJsonPath('data.nombre', 'Bodega Central');
    $response->assertJsonPath('data.documento', '20123456789');
    expect($response->json('data.id'))->toBe(CompradorModel::sole()->id);
});
