<?php

use App\Application\Calidad\Contracts\MuestreosCalidadQuery;
use App\Application\Calidad\ElegirMuestraAleatoria;
use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\RangoMuestreo;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Application\Calidad\RegistrarEntregaMuestreada;
use App\Application\Calidad\SeleccionarMuestraCalidad;
use App\Domain\Calidad\MuestraYaSeleccionadaException;
use App\Domain\Calidad\MuestreoCalidad;
use App\Domain\Calidad\MuestreoCalidadRepository;
use App\Domain\Shared\Enums\EstadoMuestreo;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Domain\Shared\Enums\TipoControlMuestreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function muestreo_escenario(int $entregas = 1, ?DateTimeImmutable $fechaHora = null): array
{
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $analista = fixture_usuario('CALIDAD');
    $lista = [];

    for ($i = 0; $i < $entregas; $i++) {
        $lista[] = fixture_entrega(fixture_proveedor($zona), $acopiador, 30.0 + $i, $fechaHora);
    }

    return [$analista, $lista];
}

function seleccionar_manual(int $entregaId, int $userId): MuestreoCalidad
{
    return app(SeleccionarMuestraCalidad::class)->execute(
        entregaId: $entregaId,
        analistaUserId: $userId,
        metodo: MetodoSeleccionMuestreo::MANUAL,
        tipoControl: TipoControlMuestreo::RUTINARIO,
    );
}

it('caso 1: con 100 entregas y 5 seleccionadas, solo hay 5 muestras pendientes', function () {
    [$analista, $entregas] = muestreo_escenario(100);

    foreach (array_slice($entregas, 0, 5) as $entrega) {
        seleccionar_manual($entrega->id, $analista->id);
    }

    $query = app(MuestreosCalidadQuery::class);

    expect($query->pendientes())->toHaveCount(5);
    expect($query->kpis(new DateTimeImmutable)['pendientes'])->toBe(5);
});

it('caso 2: la selección aleatoria la hace el servidor y nunca propone una entrega ya seleccionada', function () {
    [$analista, $entregas] = muestreo_escenario(3);
    seleccionar_manual($entregas[0]->id, $analista->id);
    seleccionar_manual($entregas[1]->id, $analista->id);

    $propuesta = app(ElegirMuestraAleatoria::class)->execute(RangoMuestreo::resolver(null), $analista->id);
    expect($propuesta['entrega']['entregaId'])->toBe($entregas[2]->id);

    // Confirmar con la firma del sorteo registra la trazabilidad: quién, cuándo, ALEATORIA.
    $muestra = app(SeleccionarMuestraCalidad::class)->execute(
        entregaId: $entregas[2]->id,
        analistaUserId: $analista->id,
        metodo: MetodoSeleccionMuestreo::ALEATORIA,
        propuestaToken: $propuesta['propuesta_token'],
    );
    expect($muestra->metodoSeleccion())->toBe(MetodoSeleccionMuestreo::ALEATORIA)
        ->and($muestra->seleccionadoPorUserId())->toBe($analista->id);

    expect(app(ElegirMuestraAleatoria::class)->execute(RangoMuestreo::resolver(null), $analista->id))->toBeNull();
});

it('la selección aleatoria excluye las entregas ya vistas ("Elegir otra")', function () {
    [$analista, $entregas] = muestreo_escenario(2);

    $otra = app(ElegirMuestraAleatoria::class)->execute(RangoMuestreo::resolver(null), $analista->id, excluir: [$entregas[0]->id]);

    expect($otra['entrega']['entregaId'])->toBe($entregas[1]->id);
});

it('no ofrece para muestreo entregas de proveedores inactivos', function () {
    [$analista, $entregas] = muestreo_escenario(1);
    $entregas[0]->proveedor->update(['estado' => 'INACTIVO']);

    expect(app(ElegirMuestraAleatoria::class)->execute(RangoMuestreo::resolver(null), $analista->id))->toBeNull();
});

it('caso 3: la selección manual deja la muestra SELECCIONADA y aparece en pendientes', function () {
    [$analista, $entregas] = muestreo_escenario(1);

    $muestra = seleccionar_manual($entregas[0]->id, $analista->id);

    expect($muestra->estado())->toBe(EstadoMuestreo::SELECCIONADA);
    expect($muestra->metodoSeleccion())->toBe(MetodoSeleccionMuestreo::MANUAL);
    expect($muestra->seleccionadoPorUserId())->toBe($analista->id);

    $pendientes = app(MuestreosCalidadQuery::class)->pendientes();
    expect($pendientes)->toHaveCount(1);
    expect($pendientes[0]['entregaId'])->toBe($entregas[0]->id);
    expect($pendientes[0]['metodoSeleccion'])->toBe('MANUAL');
});

it('no duplica: otro analista no puede seleccionar una entrega ya seleccionada', function () {
    [$analista, $entregas] = muestreo_escenario(1);
    $otroAnalista = fixture_usuario('CALIDAD');
    seleccionar_manual($entregas[0]->id, $analista->id);

    seleccionar_manual($entregas[0]->id, $otroAnalista->id);
})->throws(MuestraYaSeleccionadaException::class, 'Esta entrega ya fue seleccionada para análisis.');

it('reenviar la misma selección del mismo analista (reintento offline) no duplica ni falla', function () {
    [$analista, $entregas] = muestreo_escenario(1);

    $primera = seleccionar_manual($entregas[0]->id, $analista->id);
    $reintento = seleccionar_manual($entregas[0]->id, $analista->id);

    expect($reintento->id())->toBe($primera->id());
    $this->assertDatabaseCount('muestreos_calidad', 1);
});

it('caso 4: al confirmar el análisis la muestra queda ANALIZADA, sale de pendientes y entra al historial', function () {
    [$analista, $entregas] = muestreo_escenario(1);
    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');
    seleccionar_manual($entregas[0]->id, $analista->id);

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregas[0]->id, valores: ['GRASA' => 4.2]);

    expect(app(MuestreoCalidadRepository::class)->buscarPorEntregaId($entregas[0]->id)->estado())->toBe(EstadoMuestreo::EN_ANALISIS);
    expect(app(MuestreosCalidadQuery::class)->pendientes())->toHaveCount(1);

    app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $analista->id);

    expect(app(MuestreoCalidadRepository::class)->buscarPorEntregaId($entregas[0]->id)->estado())->toBe(EstadoMuestreo::ANALIZADA);
    expect(app(MuestreosCalidadQuery::class)->pendientes())->toBeEmpty();
    expect(app(MuestreosCalidadQuery::class)->kpis(new DateTimeImmutable)['analizadas_hoy'])->toBe(1);
    $this->assertDatabaseHas('analisis_calidad', ['entrega_id' => $entregas[0]->id, 'estado' => 'CONFIRMADO']);
});

it('caso 7: una entrega con 30 minutos desde el acopio se muestrea y analiza normalmente', function () {
    [$analista, $entregas] = muestreo_escenario(1, new DateTimeImmutable('-30 minutes'));
    $parametro = fixture_parametro('PH');
    fixture_regla_calidad($parametro->id, 6.5, 6.8, 'RECHAZADA');

    seleccionar_manual($entregas[0]->id, $analista->id);
    $analisis = app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregas[0]->id, valores: ['PH' => 6.6], exigirMuestra: true);
    $confirmado = app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $analista->id);

    expect($confirmado->estaConfirmado())->toBeTrue();
});

it('no permite seleccionar una entrega ya analizada', function () {
    [$analista, $entregas] = muestreo_escenario(1);
    $entregas[0]->update(['estado' => 'ANALIZADA']);

    seleccionar_manual($entregas[0]->id, $analista->id);
})->throws(DomainException::class, 'Esta entrega ya fue analizada.');

it('guarda la hora real del evento offline, separada de la hora de sincronización', function () {
    [$analista, $entregas] = muestreo_escenario(1);
    $seleccion = new DateTimeImmutable('-24 minutes');
    $captura = new DateTimeImmutable('-20 minutes');
    $analizado = new DateTimeImmutable('-18 minutes');

    app(SeleccionarMuestraCalidad::class)->execute(
        entregaId: $entregas[0]->id,
        analistaUserId: $analista->id,
        metodo: MetodoSeleccionMuestreo::MANUAL,
        fechaSeleccion: $seleccion,
    );
    fixture_parametro('GRASA');
    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entregas[0]->id,
        valores: ['GRASA' => 4.0],
        fechaAnalisis: $analizado,
        fechaCapturaLocal: $captura,
    );

    $muestra = app(MuestreoCalidadRepository::class)->buscarPorEntregaId($entregas[0]->id);
    expect($muestra->fechaSeleccion()->format('Y-m-d H:i'))->toBe($seleccion->format('Y-m-d H:i'));
    expect($muestra->fechaCapturaLocal()->format('Y-m-d H:i'))->toBe($captura->format('Y-m-d H:i'));
    expect($analisis->fechaAnalisis()->format('Y-m-d H:i'))->toBe($analizado->format('Y-m-d H:i'));
});

it('nunca acepta una hora de evento futura (reloj del teléfono adelantado)', function () {
    [$analista, $entregas] = muestreo_escenario(1);

    $muestra = app(SeleccionarMuestraCalidad::class)->execute(
        entregaId: $entregas[0]->id,
        analistaUserId: $analista->id,
        metodo: MetodoSeleccionMuestreo::MANUAL,
        fechaSeleccion: new DateTimeImmutable('+3 hours'),
    );

    expect($muestra->fechaSeleccion() <= new DateTimeImmutable)->toBeTrue();
});

it('el muestreo directo crea la entrega con su muestra DIRECTA pendiente de análisis', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona, '45678912');
    $analista = fixture_usuario('CALIDAD');

    $entrega = app(RegistrarEntregaMuestreada::class)->execute('DNI', '45678912', $analista->id, 2.0);

    $muestra = app(MuestreoCalidadRepository::class)->buscarPorEntregaId($entrega->id());
    expect($muestra->metodoSeleccion())->toBe(MetodoSeleccionMuestreo::DIRECTA);
    expect($muestra->estado())->toBe(EstadoMuestreo::SELECCIONADA);
    expect(app(MuestreosCalidadQuery::class)->pendientes())->toHaveCount(1);
});
