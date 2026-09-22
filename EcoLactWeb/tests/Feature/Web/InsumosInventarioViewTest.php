<?php

use App\Application\Acopio\RegistrarEntrega;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de insumos muestra insumo, tipo, unidad, stock actual y estado, con la leche como insumo del sistema', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(1350.0, $admin->id);
    fixture_insumo('Cuajo', 'ml', 850.0, 100.0, $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.insumos.index'));

    $response->assertOk();
    $response->assertSeeInOrder(['Leche', 'Del sistema', 'Materia prima', 'L', '1,350 L']);
    $response->assertSee('850 ml');
    $response->assertSee('Disponible');
    // La leche no tiene botón de desactivar ni de compra: solo las entregas la alimentan.
    $response->assertDontSee('Desactivar Leche');
    $response->assertSee('La leche no se compra');
});

it('el administrador registra un insumo con tipo y unidad base y el stock inicial queda como movimiento', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post(route('admin.insumos.store'), [
        'nombre' => 'Azúcar', 'tipo' => 'INSUMO', 'unidad_base' => 'g',
        'descripcion' => 'Azúcar blanca', 'stock_minimo' => 500, 'stock_inicial' => 2000,
    ]);

    $response->assertRedirect();
    $insumo = InsumoModel::where('nombre', 'Azúcar')->firstOrFail();
    expect($insumo->unidad_base)->toBe('g');
    expect($insumo->codigo)->toBeNull();
    expect((float) $insumo->stock_actual)->toBe(2000.0);
    expect(InsumoMovimientoModel::where('insumo_id', $insumo->id)->count())->toBe(1);
});

it('valida el alta: unidad base válida y nombre único', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->post(route('admin.insumos.store'), [
        'nombre' => 'Leche', 'tipo' => 'INSUMO', 'unidad_base' => 'libras', 'stock_minimo' => 0,
    ])->assertSessionHasErrors(['nombre', 'unidad_base']);

    expect(InsumoModel::where('nombre', 'Leche')->count())->toBe(1);
});

it('la leche no se puede desactivar ni cambiar de tipo desde la interfaz, pero sí ajustar su stock mínimo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $leche = fixture_leche();

    $desactivar = $this->actingAs($admin)->patch(route('admin.insumos.update', $leche->id), [
        'nombre' => 'Leche', 'stock_minimo' => 0, 'estado' => 'INACTIVO',
    ]);
    $desactivar->assertSessionHas('error');
    expect($leche->fresh()->estado)->toBe('ACTIVO');

    $tipo = $this->actingAs($admin)->patch(route('admin.insumos.update', $leche->id), [
        'nombre' => 'Leche', 'stock_minimo' => 0, 'estado' => 'ACTIVO', 'tipo' => 'OTRO',
    ]);
    $tipo->assertSessionHas('error');
    expect($leche->fresh()->tipo)->toBe('MATERIA_PRIMA');

    $ok = $this->actingAs($admin)->patch(route('admin.insumos.update', $leche->id), [
        'nombre' => 'Leche', 'stock_minimo' => 250, 'estado' => 'ACTIVO', 'descripcion' => 'Leche cruda',
    ]);
    $ok->assertSessionHas('success');
    expect((float) $leche->fresh()->stock_minimo)->toBe(250.0);
    expect($leche->fresh()->codigo)->toBe('LECHE');
});

it('un insumo común se puede desactivar y volver a activar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'kg', 5.0, 0.0, $admin->id);

    $this->actingAs($admin)->patch(route('admin.insumos.update', $sal->id()), ['nombre' => 'Sal', 'stock_minimo' => 0, 'estado' => 'INACTIVO'])->assertSessionHas('success');
    expect(InsumoModel::find($sal->id())->estado)->toBe('INACTIVO');

    $this->actingAs($admin)->patch(route('admin.insumos.update', $sal->id()), ['nombre' => 'Sal', 'stock_minimo' => 0, 'estado' => 'ACTIVO'])->assertSessionHas('success');
    expect(InsumoModel::find($sal->id())->estado)->toBe('ACTIVO');
});

it('registra una compra convirtiendo la unidad a la base y rechaza comprar leche', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $azucar = fixture_insumo('Azúcar', 'g', 0.0, 0.0, $admin->id);

    $this->actingAs($admin)->post(route('admin.insumos.compra', $azucar->id()), ['cantidad' => 50, 'unidad' => 'kg'])->assertSessionHas('success');
    expect((float) InsumoModel::find($azucar->id())->stock_actual)->toBe(50000.0);

    $this->actingAs($admin)->post(route('admin.insumos.compra', fixture_leche()->id), ['cantidad' => 10])->assertSessionHas('error');
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(0.0);
});

it('un ajuste de stock (conteo físico) queda como movimiento y no permite dejar stock negativo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'kg', 10.0, 0.0, $admin->id);

    $this->actingAs($admin)->post(route('admin.insumos.ajuste', $sal->id()), ['tipo' => 'SALIDA', 'motivo' => 'Merma', 'cantidad' => 3])->assertSessionHas('success');
    expect((float) InsumoModel::find($sal->id())->stock_actual)->toBe(7.0);

    $this->actingAs($admin)->post(route('admin.insumos.ajuste', $sal->id()), ['tipo' => 'SALIDA', 'motivo' => 'Merma', 'cantidad' => 30])->assertSessionHas('error');
    expect((float) InsumoModel::find($sal->id())->stock_actual)->toBe(7.0);
});

it('el historial desplegable de la leche muestra el ingreso por acopio con su entrega de origen', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 20.0);

    $response = $this->actingAs($admin)->get(route('admin.insumos.index'));

    $response->assertOk();
    $response->assertSee('Ingreso por acopio');
    $response->assertSee('+20 L');

    $ficha = $this->actingAs($admin)->get(route('admin.insumos.show', fixture_leche()->id));
    $ficha->assertOk();
    $ficha->assertSee('Entrega #'.$entrega->id());
});

it('una entrega registrada desde el panel del administrador aumenta el stock de leche y se puede anular', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $this->actingAs($admin)->post(route('admin.entregas.store'), [
        'metodo_identificacion' => 'DNI', 'identificador' => $proveedor->dni, 'litros' => 35,
    ])->assertRedirect();
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(35.0);

    $entrega = EntregaModel::firstOrFail();

    $this->actingAs($admin)->patch(route('admin.entregas.anular', $entrega->id), [])->assertSessionHasErrors('motivo');
    $this->actingAs($admin)->patch(route('admin.entregas.anular', $entrega->id), ['motivo' => 'Duplicada'])->assertSessionHas('success');

    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(0.0);
    expect($entrega->fresh()->estado)->toBe('ANULADA');
    expect(InsumoMovimientoModel::where('origen_id', $entrega->id)->pluck('motivo')->all())->toBe(['INGRESO_ACOPIO', 'REVERSO']);
});

it('un encargado de planta no puede anular entregas', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $zona = fixture_zona();
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 10.0);

    $this->actingAs($encargado)->patch(route('admin.entregas.anular', $entrega->id), ['motivo' => 'x'])->assertForbidden();
});
