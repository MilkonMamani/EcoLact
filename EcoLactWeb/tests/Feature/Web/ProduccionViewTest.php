<?php

use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarProduccion;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla principal de producción muestra los KPIs y el listado de lotes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $lote = fixture_lote(30.0, $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.produccion.index'));

    $response->assertOk();
    $response->assertSee('Lotes en proceso');
    $response->assertSee($lote->codigo());
    $response->assertSee('30 L');
});

it('el detalle del lote muestra la leche, los insumos y los productos planificados y obtenidos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(500.0, $admin->id);
    $sal = fixture_insumo('Sal fina', 'g', 5000.0, 0.0, $admin->id);
    $producto = fixture_producto('Queso Vista');
    fixture_receta($producto->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $sal->id(), 'cantidad' => 20.0, 'unidad' => 'g'],
    ], $admin->id);

    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 4]], $admin->id);

    $enProceso = $this->actingAs($admin)->get(route('admin.produccion.show', $lote->id()));
    $enProceso->assertOk();
    $enProceso->assertSee($lote->codigo());
    $enProceso->assertSee('Queso Vista');
    $enProceso->assertSee('Sal fina');
    $enProceso->assertSee('40 L');
    $enProceso->assertSee('Registrar resultado y finalizar');

    app(FinalizarProduccion::class)->execute($lote->id(), [ProduccionDetalleModel::first()->id => 3.0], $admin->id);

    $finalizado = $this->actingAs($admin)->get(route('admin.produccion.show', $lote->id()));
    $finalizado->assertOk();
    $finalizado->assertSee('Resultado final');
    $finalizado->assertDontSee('Registrar resultado y finalizar');
});

it('el modal sin productos con receta muestra el estado vacío y el botón a Recetas solo para el administrador', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    fixture_producto('Producto sin receta');

    $vistaAdmin = $this->actingAs($admin)->get(route('admin.produccion.index'))->assertOk();
    $vistaAdmin->assertSee('No hay productos disponibles para producir.');
    $vistaAdmin->assertSee('Primero configura una receta.');
    $vistaAdmin->assertSee('Ir a Recetas');
    $vistaAdmin->assertSee(route('admin.recetas.index'));

    $vistaPlanta = $this->actingAs($encargado)->get(route('planta.produccion.index'))->assertOk();
    $vistaPlanta->assertSee('No hay productos disponibles para producir.');
    $vistaPlanta->assertDontSee('Ir a Recetas');
});

it('la ruta interna de crear redirige al historial y abre el modal', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.produccion.create'))->assertRedirect(route('admin.produccion.index', ['nueva' => 1]));
    $this->actingAs($admin)->get(route('admin.produccion.index', ['nueva' => 1]))->assertSee('data-abrir="1"', false);
    $this->actingAs($admin)->get(route('admin.produccion.index'))->assertSee('data-abrir="0"', false);
});
