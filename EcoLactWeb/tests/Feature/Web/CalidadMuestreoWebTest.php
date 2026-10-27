<?php

use App\Application\Acopio\AnularEntrega;
use App\Application\Calidad\Contracts\MuestreosCalidadQuery;
use App\Application\Calidad\ElegirMuestraAleatoria;
use App\Application\Calidad\RangoMuestreo;
use App\Application\Calidad\SeleccionarMuestraCalidad;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Infrastructure\Persistence\Models\MuestreoCalidadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

/** Juan 40, María 35, Pedro 48, Lucía 42, Carlos 37 (ejemplo del encargado de planta). */
function cinco_entregas(): array
{
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $entregas = [];
    foreach (['Juan' => 40.0, 'María' => 35.0, 'Pedro' => 48.0, 'Lucía' => 42.0, 'Carlos' => 37.0] as $nombre => $litros) {
        $proveedor = fixture_proveedor($zona);
        $proveedor->update(['nombres' => $nombre]);
        $entregas[$nombre] = fixture_entrega($proveedor, $acopiador, $litros);
    }

    return $entregas;
}

it('de 5 entregas, solo las 2 seleccionadas son muestras pendientes (Web)', function () {
    $analista = fixture_usuario('CALIDAD');
    $entregas = cinco_entregas();

    foreach (['María', 'Lucía'] as $nombre) {
        $this->actingAs($analista)->post(route('calidad.muestreo.store'), [
            'entrega_id' => $entregas[$nombre]->id,
            'metodo_seleccion' => 'MANUAL',
            'tipo_control' => 'RUTINARIO',
        ])->assertRedirect(route('calidad.analisis-calidad.index'));
    }

    $pendientes = app(MuestreosCalidadQuery::class)->pendientes();
    expect($pendientes)->toHaveCount(2)
        ->and(collect($pendientes)->pluck('entregaId')->all())->toEqualCanonicalizing([$entregas['María']->id, $entregas['Lucía']->id]);

    $this->actingAs($analista)->get(route('calidad.dashboard'))
        ->assertOk()
        ->assertSee('Muestras pendientes')
        ->assertSee(route('calidad.entregas.analisis.index', $entregas['María']->id), false)
        ->assertDontSee(route('calidad.entregas.analisis.index', $entregas['Juan']->id), false);
});

it('Web: elegir al azar muestra la propuesta del servidor y la confirma como ALEATORIA', function () {
    $analista = fixture_usuario('CALIDAD');
    $entregas = cinco_entregas();

    $html = $this->actingAs($analista)->get(route('calidad.muestreo.index', ['modo' => 'aleatoria']))
        ->assertOk()
        ->assertSee('Confirmar muestra')
        ->assertSee('Elegir otra')
        ->getContent();

    preg_match('/name="entrega_id" value="(\d+)"/', $html, $entrega);
    preg_match('/name="propuesta_token" value="([^"]+)"/', $html, $token);

    $this->post(route('calidad.muestreo.store'), [
        'entrega_id' => (int) $entrega[1],
        'metodo_seleccion' => 'ALEATORIA',
        'propuesta_token' => html_entity_decode($token[1]),
    ])->assertRedirect(route('calidad.analisis-calidad.index'));

    $this->assertDatabaseHas('muestreos_calidad', [
        'entrega_id' => (int) $entrega[1],
        'metodo_seleccion' => 'ALEATORIA',
        'seleccionado_por_user_id' => $analista->id,
    ]);
    expect(collect($entregas)->pluck('id'))->toContain((int) $entrega[1]);
});

it('una selección ALEATORIA sin firma del sorteo es rechazada (el cliente no decide el azar)', function () {
    $analista = fixture_usuario('CALIDAD');
    $entregas = cinco_entregas();

    $this->actingAs($analista)->post(route('calidad.muestreo.store'), [
        'entrega_id' => $entregas['Juan']->id,
        'metodo_seleccion' => 'ALEATORIA',
        'propuesta_token' => 'inventado',
    ])->assertSessionHas('error');

    expect(MuestreoCalidadModel::count())->toBe(0);
});

it('la firma de un sorteo no sirve para otro analista', function () {
    $analista = fixture_usuario('CALIDAD');
    $otro = fixture_usuario('CALIDAD');
    cinco_entregas();
    $propuesta = app(ElegirMuestraAleatoria::class)->execute(RangoMuestreo::resolver(null), $analista->id);

    app(SeleccionarMuestraCalidad::class)->execute(
        entregaId: $propuesta['entrega']['entregaId'],
        analistaUserId: $otro->id,
        metodo: MetodoSeleccionMuestreo::ALEATORIA,
        propuestaToken: $propuesta['propuesta_token'],
    );
})->throws(DomainException::class, 'El sorteo venció o no corresponde a esta entrega.');

it('Web: si otro analista ya la eligió, avisa y no duplica', function () {
    $entregas = cinco_entregas();
    fixture_muestra($entregas['Pedro']);

    $this->actingAs(fixture_usuario('CALIDAD'))->post(route('calidad.muestreo.store'), [
        'entrega_id' => $entregas['Pedro']->id,
        'metodo_seleccion' => 'MANUAL',
    ])->assertSessionHas('error', 'Esta entrega ya fue seleccionada para análisis.');

    expect(MuestreoCalidadModel::where('entrega_id', $entregas['Pedro']->id)->count())->toBe(1);
});

it('Web: la lista manual solo ofrece entregas elegibles', function () {
    $entregas = cinco_entregas();
    fixture_muestra($entregas['María']);
    $entregas['Pedro']->update(['estado' => 'ANALIZADA']);
    $entregas['Carlos']->update(['estado' => 'ANULADA']);

    $elegibles = $this->actingAs(fixture_usuario('CALIDAD'))
        ->get(route('calidad.muestreo.index', ['modo' => 'manual']))
        ->assertOk()
        ->viewData('elegibles');

    expect(collect($elegibles)->pluck('entregaId')->all())
        ->toEqualCanonicalizing([$entregas['Juan']->id, $entregas['Lucía']->id]);
});

it('entrega anulada no es elegible y anularla cancela su muestra pendiente', function () {
    $analista = fixture_usuario('CALIDAD');
    $entregas = cinco_entregas();
    fixture_muestra($entregas['Lucía'], $analista->id);

    app(AnularEntrega::class)->execute($entregas['Lucía']->id, 'Registro duplicado', fixture_usuario('ADMINISTRADOR')->id);

    $this->assertDatabaseHas('muestreos_calidad', ['entrega_id' => $entregas['Lucía']->id, 'estado' => 'CANCELADA']);
    expect(app(MuestreosCalidadQuery::class)->pendientes())->toBeEmpty();

    $entregas['Juan']->update(['estado' => 'ANULADA']);
    app(SeleccionarMuestraCalidad::class)->execute($entregas['Juan']->id, $analista->id, MetodoSeleccionMuestreo::MANUAL);
})->throws(DomainException::class, 'Esta entrega fue anulada y no se puede muestrear.');

it('los análisis históricos sin muestreo siguen visibles en el historial y su detalle', function () {
    $analista = fixture_usuario('CALIDAD');
    $entregas = cinco_entregas();
    $analisis = fixture_analisis_confirmado($entregas['Juan'], 'ACEPTADA', $analista->id);
    $entregas['Juan']->update(['estado' => 'ANALIZADA']);

    $this->actingAs($analista)->get(route('calidad.analisis-calidad.index', ['tab' => 'historial']))
        ->assertOk()
        ->assertSee(route('calidad.analisis-calidad.show', $analisis->id), false);

    $this->actingAs($analista)->get(route('calidad.analisis-calidad.show', $analisis->id))
        ->assertOk()
        ->assertSee('Sin muestreo (registro anterior)');
});
