<?php

use App\Application\Acopio\CorregirLitrosEntrega;
use App\Application\Pago\Contracts\EntregasPagablesQuery;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Infrastructure\Persistence\Models\AuditoriaModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function entrega_para_editar(float $litros = 45.0): EntregaModel
{
    $zona = fixture_zona();

    return fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), $litros);
}

it('el administrador corrige los litros y solo cambia ese campo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    $antes = $entrega->fresh()->only(['proveedor_id', 'acopiador_id', 'zona_id', 'fecha_hora', 'metodo_identificacion', 'estado', 'observacion', 'registrado_por_user_id']);

    $response = $this->actingAs($admin)->patchJson(route('admin.entregas.litros.update', $entrega->id), [
        'litros' => 48.5,
        'proveedor_id' => 999,
        'estado' => 'ANALIZADA',
    ]);

    $response->assertOk()->assertJson([
        'message' => 'Litros actualizados correctamente.',
        'litros_formateados' => '48.50 L',
    ]);
    $despues = $entrega->fresh();
    expect((float) $despues->litros)->toBe(48.5)
        ->and($despues->only(array_keys($antes)))->toEqual($antes);
});

it('registra en auditorias el valor anterior, el nuevo, el usuario y el contexto HTTP', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);

    $this->actingAs($admin)
        ->withHeader('User-Agent', 'PruebaEcoLact/1.0')
        ->patchJson(route('admin.entregas.litros.update', $entrega->id), ['litros' => '48.50'])
        ->assertOk();

    $auditoria = AuditoriaModel::sole();
    expect($auditoria->user_id)->toBe($admin->id)
        ->and($auditoria->accion)->toBe(CorregirLitrosEntrega::ACCION_AUDITORIA)
        ->and($auditoria->tabla_afectada)->toBe('entregas')
        ->and($auditoria->registro_id)->toBe($entrega->id)
        ->and($auditoria->datos_anteriores)->toEqual(['litros' => 45.0])
        ->and($auditoria->datos_nuevos)->toEqual(['litros' => 48.5])
        ->and($auditoria->ip)->toBe('127.0.0.1')
        ->and($auditoria->user_agent)->toBe('PruebaEcoLact/1.0')
        ->and($auditoria->created_at)->not->toBeNull();
});

it('un usuario que no es administrador no puede corregir litros', function () {
    $entrega = entrega_para_editar(45.0);
    $acopiador = $entrega->acopiador->user;

    $this->actingAs($acopiador)
        ->patchJson(route('admin.entregas.litros.update', $entrega->id), ['litros' => 50])
        ->assertForbidden();

    expect((float) $entrega->fresh()->litros)->toBe(45.0);
    expect(AuditoriaModel::count())->toBe(0);
});

it('rechaza litros no válidos sin tocar la entrega', function (mixed $litros, string $mensaje) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);

    $this->actingAs($admin)
        ->patchJson(route('admin.entregas.litros.update', $entrega->id), ['litros' => $litros])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['litros' => $mensaje]);

    expect((float) $entrega->fresh()->litros)->toBe(45.0);
    expect(AuditoriaModel::count())->toBe(0);
})->with([
    'negativo' => [-5, 'Los litros deben ser mayores a 0.'],
    'cero' => [0, 'Los litros deben ser mayores a 0.'],
    'texto' => ['abc', 'Los litros deben ser un número.'],
    'vacío' => ['', 'Ingresa la cantidad de litros.'],
    'más de 2 decimales' => ['12.345', 'Los litros admiten como máximo 2 decimales.'],
]);

it('no permite corregir una entrega que ya no está pendiente de análisis', function (string $estado, string $mensaje) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    $entrega->update(['estado' => $estado]);

    $this->actingAs($admin)
        ->patchJson(route('admin.entregas.litros.update', $entrega->id), ['litros' => 50])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['litros' => $mensaje]);

    expect((float) $entrega->fresh()->litros)->toBe(45.0);
    expect(AuditoriaModel::count())->toBe(0);
})->with([
    'analizada' => ['ANALIZADA', 'No se puede modificar esta entrega porque ya tiene un análisis de calidad confirmado.'],
    'anulada' => ['ANULADA', 'No se puede modificar esta entrega porque está anulada.'],
]);

it('no permite corregir una entrega que ya forma parte de un pago realizado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    // Armar un pago PAGADO real exige configuración, precio y liquidación; aquí
    // basta con que la consulta del módulo de pagos lo reporte.
    $this->mock(EntregasPagablesQuery::class)
        ->shouldReceive('estaEnPagoRealizado')->with($entrega->id)->andReturnTrue();

    $this->actingAs($admin)
        ->patchJson(route('admin.entregas.litros.update', $entrega->id), ['litros' => 50])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['litros' => 'No se puede modificar esta entrega porque ya forma parte de un pago realizado al proveedor.']);

    expect((float) $entrega->fresh()->litros)->toBe(45.0);
});

it('si falla la actualización de la entrega no queda auditoría', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    $real = app(EntregaRepository::class);
    $this->mock(EntregaRepository::class, function ($mock) use ($real) {
        $mock->shouldReceive('buscarPorId')->andReturnUsing(fn (int $id) => $real->buscarPorId($id));
        $mock->shouldReceive('actualizar')->andThrow(new RuntimeException('Fallo al guardar la entrega'));
    });

    expect(fn () => app(CorregirLitrosEntrega::class)->execute($entrega->id, 50.0, $admin->id))
        ->toThrow(RuntimeException::class, 'Fallo al guardar la entrega');

    expect(AuditoriaModel::count())->toBe(0);
    expect((float) $entrega->fresh()->litros)->toBe(45.0);
});

it('si falla la auditoría la entrega no queda actualizada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    $this->mock(AuditoriaRepository::class)
        ->shouldReceive('registrar')->andThrow(new RuntimeException('Fallo al auditar'));

    expect(fn () => app(CorregirLitrosEntrega::class)->execute($entrega->id, 50.0, $admin->id))
        ->toThrow(RuntimeException::class, 'Fallo al auditar');

    expect((float) $entrega->fresh()->litros)->toBe(45.0);
});

it('el modal "Ver entrega" muestra el botón Editar solo mientras la entrega está pendiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $pendiente = entrega_para_editar(45.0);
    $analizada = entrega_para_editar(30.0);
    $analizada->update(['estado' => 'ANALIZADA']);

    $this->actingAs($admin)->get(route('admin.entregas.detalle', $pendiente->id))
        ->assertOk()
        ->assertSeeText(['Ver entrega #'.$pendiente->id, 'Información de la entrega', 'Resumen del registro', 'Estado del flujo', 'Análisis de calidad', '45.00 L'])
        ->assertSee('data-litros-editar', false);

    $this->actingAs($admin)->get(route('admin.entregas.detalle', $analizada->id))
        ->assertOk()
        ->assertDontSee('data-litros-editar', false);
});

it('permite corregir los litros hasta 10 minutos después de la hora de acopio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    $this->travelTo($entrega->fecha_hora->addMinutes(10));

    $this->actingAs($admin)
        ->patchJson(route('admin.entregas.litros.update', $entrega->id), ['litros' => 48.5])
        ->assertOk();

    expect((float) $entrega->fresh()->litros)->toBe(48.5);
});

it('no permite corregir los litros pasados 10 minutos de la hora de acopio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    $this->travelTo($entrega->fecha_hora->addMinutes(10)->addSecond());

    $this->actingAs($admin)
        ->patchJson(route('admin.entregas.litros.update', $entrega->id), ['litros' => 48.5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['litros' => 'No se puede modificar esta entrega porque ya pasaron más de 10 minutos desde la hora de acopio.']);

    expect((float) $entrega->fresh()->litros)->toBe(45.0);
    expect(AuditoriaModel::count())->toBe(0);
});

it('el modal oculta el botón Editar cuando ya pasaron los 10 minutos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    $this->travelTo($entrega->fecha_hora->addMinutes(11));

    $this->actingAs($admin)->get(route('admin.entregas.detalle', $entrega->id))
        ->assertOk()
        ->assertDontSee('data-litros-editar', false);
});

it('la edición completa de la entrega tampoco cambia los litros pasados 10 minutos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = entrega_para_editar(45.0);
    $this->travelTo($entrega->fecha_hora->addMinutes(11));

    $this->actingAs($admin)
        ->patch(route('admin.entregas.update', $entrega->id), ['litros' => 60, 'motivo_modificacion' => 'Corrección'])
        ->assertSessionHas('error', 'No se pueden modificar los litros porque ya pasaron más de 10 minutos desde la hora de acopio.');

    expect((float) $entrega->fresh()->litros)->toBe(45.0);
});
