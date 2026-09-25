<?php

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\Contracts\EntregasCalidadListadoQuery;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Tres entregas como en Acopio: dos sin análisis y una analizada (ACEPTADA).
 *
 * @return array{0: User, 1: array<string, EntregaModel>}
 */
function tres_entregas_de_acopio(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $luis = fixture_proveedor($zona, '72100005');
    $luis->update(['nombres' => 'Luis', 'apellidos' => 'Calcina Mamani']);
    $milkon = fixture_proveedor($zona, '77777777');
    $milkon->update(['nombres' => 'Milkon', 'apellidos' => 'Mamani']);
    $acopiador = fixture_acopiador($zona);

    $entregas = [
        'luis' => fixture_entrega($luis, $acopiador, 20.0, new DateTimeImmutable('2026-09-17 13:34')),
        'milkon_pendiente' => fixture_entrega($milkon, $acopiador, 20.0, new DateTimeImmutable('2026-09-15 14:34')),
        'milkon_analizada' => fixture_entrega($milkon, $acopiador, 30.0, new DateTimeImmutable('2026-09-14 22:58')),
    ];
    fixture_analisis_confirmado($entregas['milkon_analizada'], 'ACEPTADA', $admin->id);
    $entregas['milkon_analizada']->update(['estado' => 'ANALIZADA']);

    return [$admin, $entregas];
}

it('Calidad muestra también las entregas sin análisis como Pendiente', function () {
    [$admin, $entregas] = tres_entregas_de_acopio();

    $html = $this->actingAs($admin)->get(route('admin.analisis-calidad.index'))
        ->assertOk()
        ->assertSeeInOrder(['Fecha', 'Proveedor', 'Entrega', 'Zona', 'Resultado', 'Estado', 'Acción'])
        ->assertSee('Luis Calcina Mamani')
        ->assertSee('Collana 1')
        ->assertSee('17/09/2026')
        ->assertSee('15/09/2026')
        ->assertSee('14/09/2026')
        ->getContent();

    expect(preg_match_all('/Pendiente\s*<\/span>/', $html))->toBe(2)
        ->and($html)->toContain(route('admin.entregas.analisis-calidad.index', $entregas['luis']))
        ->and($html)->toContain(route('admin.entregas.analisis-calidad.index', $entregas['milkon_pendiente']))
        ->and($html)->toContain(route('admin.analisis-calidad.show', AnalisisCalidadModel::firstOrFail()));
});

it('mostrar Pendiente no crea análisis ficticios', function () {
    [$admin] = tres_entregas_de_acopio();

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index'))->assertOk();

    expect(AnalisisCalidadModel::count())->toBe(1);
});

it('Acopio y Calidad cuentan lo mismo: 3 entregas, 2 pendientes y 1 analizada', function () {
    [$admin] = tres_entregas_de_acopio();

    $acopio = $this->actingAs($admin)->get(route('admin.entregas.index'))->assertOk();
    $calidad = app(EntregasCalidadListadoQuery::class)->obtener(AnalisisCalidadFiltro::resolver(null, null, null, null, null, null, null));

    $acopio->assertSeeText('Mostrando 1 a 3 de 3 entregas');

    expect($calidad->kpis)->toBe(['pendientes' => 2, 'analizadas' => 1, 'aceptadas' => 1, 'observadas' => 0, 'rechazadas' => 0])
        ->and($calidad->filas)->toHaveCount(3);
});

it('los KPIs de Calidad se dibujan con los conteos reales', function () {
    [$admin] = tres_entregas_de_acopio();

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index'))
        ->assertOk()
        ->assertSeeInOrder(['Pendientes de análisis', '2', 'Analizadas', '1', 'Aceptadas', '1', 'Observadas', '0', 'Rechazadas', '0']);
});

it('una entrega anulada no aparece en Calidad', function () {
    [$admin, $entregas] = tres_entregas_de_acopio();
    $entregas['luis']->update(['estado' => 'ANULADA']);

    $calidad = app(EntregasCalidadListadoQuery::class)->obtener(AnalisisCalidadFiltro::resolver(null, null, null, null, null, null, null));

    expect($calidad->filas)->toHaveCount(2)->and($calidad->kpis['pendientes'])->toBe(1);
});

it('los filtros de Calidad soportan estado Pendiente, resultado, zona y fecha', function () {
    [$admin, $entregas] = tres_entregas_de_acopio();

    $consulta = fn (?string $q = null, ?int $zona = null, ?string $resultado = null, ?string $estado = null, ?string $fecha = null, ?string $desde = null, ?string $hasta = null) => app(EntregasCalidadListadoQuery::class)
        ->obtener(AnalisisCalidadFiltro::resolver($q, $zona, $resultado, $estado, $fecha, $desde, $hasta));

    expect($consulta(estado: 'PENDIENTE')->filas)->toHaveCount(2)
        ->and($consulta(estado: 'CONFIRMADO')->filas)->toHaveCount(1)
        ->and($consulta(resultado: 'ACEPTADA')->filas)->toHaveCount(1)
        ->and($consulta(resultado: 'OBSERVADA')->filas)->toHaveCount(0)
        ->and($consulta(zona: $entregas['luis']->zona_id)->filas)->toHaveCount(3)
        ->and($consulta(zona: 999)->filas)->toHaveCount(0)
        ->and($consulta(fecha: 'personalizado', desde: '2026-09-15', hasta: '2026-09-15')->filas)->toHaveCount(1);
});

it('el buscador de Calidad encuentra entregas pendientes por proveedor, DNI e ID de entrega', function () {
    [$admin, $entregas] = tres_entregas_de_acopio();

    $buscar = fn (string $q) => app(EntregasCalidadListadoQuery::class)->obtener(AnalisisCalidadFiltro::resolver($q, null, null, null, null, null, null))->filas;

    expect($buscar('Luis'))->toHaveCount(1)
        ->and($buscar('72100005'))->toHaveCount(1)
        ->and(collect($buscar((string) $entregas['milkon_pendiente']->id))->pluck('entregaId')->all())->toContain($entregas['milkon_pendiente']->id)
        ->and($buscar('Milkon'))->toHaveCount(2)
        ->and($buscar('Milkon')[0]['estado'])->toBe('PENDIENTE');
});

it('Analizar: la entrega pendiente muestra el formulario, se registra, se confirma y pasa a su resultado real', function () {
    [$admin, $entregas] = tres_entregas_de_acopio();
    fixture_regla_calidad(fixture_parametro('GRASA')->id, 3.0, 6.0, 'OBSERVADA');
    $pendiente = $entregas['luis'];

    $this->actingAs($admin)->get(route('admin.entregas.analisis-calidad.index', $pendiente))
        ->assertOk()
        ->assertSee('Esta entrega todavía no tiene análisis de calidad')
        ->assertSee('name="valores[GRASA]"', false);

    expect(AnalisisCalidadModel::where('entrega_id', $pendiente->id)->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.analisis-calidad.store'), ['entrega_id' => $pendiente->id, 'valores' => ['GRASA' => 2.0]])
        ->assertSessionHasNoErrors();

    $analisis = AnalisisCalidadModel::where('entrega_id', $pendiente->id)->firstOrFail();
    expect($analisis->estado)->toBe('BORRADOR');

    $this->actingAs($admin)->patch(route('admin.analisis-calidad.finalizar', $analisis))->assertSessionHasNoErrors();

    expect($analisis->fresh()->estado)->toBe('CONFIRMADO')
        ->and($analisis->fresh()->resultado)->toBe('OBSERVADA');

    $calidad = app(EntregasCalidadListadoQuery::class)->obtener(AnalisisCalidadFiltro::resolver(null, null, null, null, null, null, null));

    expect($calidad->kpis)->toBe(['pendientes' => 1, 'analizadas' => 2, 'aceptadas' => 1, 'observadas' => 1, 'rechazadas' => 0]);

    $this->actingAs($admin)->get(route('admin.analisis-calidad.index'))->assertOk()->assertSee('Observada');
});

it('una entrega pendiente de análisis igual se acopia y se paga: Calidad no bloquea nada', function () {
    [$admin, $entregas] = tres_entregas_de_acopio();

    expect($entregas['luis']->fresh()->estado)->toBe('PENDIENTE_ANALISIS');

    $this->actingAs($admin)->get(route('admin.entregas.index'))->assertOk()->assertSee('Luis Calcina Mamani');
});
