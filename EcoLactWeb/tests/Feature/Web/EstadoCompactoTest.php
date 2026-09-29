<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('muestra solo el ícono semántico con el nombre del estado como tooltip y texto accesible', function (string $estado, string $clase, string $tooltip) {
    $html = Blade::render('<x-ui.badge compact :status="$estado" />', ['estado' => $estado]);

    expect($html)
        ->toContain('class="estado-compacto '.$clase.'"')
        ->toContain('data-tooltip="'.$tooltip.'"')
        ->toContain('<span class="sr-only">'.$tooltip.'</span>')
        ->not->toContain('rounded-full px-2.5');
})->with([
    'pendiente de análisis' => ['PENDIENTE_ANALISIS', 'is-pendiente', 'Pendiente de análisis'],
    'pendiente' => ['PENDIENTE', 'is-pendiente', 'Pendiente'],
    'analizada' => ['ANALIZADA', 'is-positivo', 'Analizada'],
    'aceptada' => ['ACEPTADA', 'is-positivo', 'Aceptada'],
    'confirmado' => ['CONFIRMADO', 'is-positivo', 'Confirmado'],
    'observada' => ['OBSERVADA', 'is-advertencia', 'Observada'],
    'rechazada' => ['RECHAZADA', 'is-negativo', 'Rechazada'],
    'anulada' => ['ANULADA', 'is-negativo', 'Anulada'],
    'sin configurar' => ['SIN_CONFIGURAR', 'is-neutro', 'Sin configurar'],
]);

it('usa doble check, reloj, advertencia y X según el tono', function () {
    $icono = fn (string $estado): string => Blade::render('<x-ui.badge compact :status="$estado" />', ['estado' => $estado]);
    $trazo = fn (string $nombre): string => Blade::render('<x-ui.icon :name="$nombre" />', ['nombre' => $nombre]);

    expect($icono('ANALIZADA'))->toContain($trazo('check-double'));
    expect($icono('PENDIENTE_ANALISIS'))->toContain($trazo('clock'));
    expect($icono('OBSERVADA'))->toContain($trazo('triangle-alert'));
    expect($icono('RECHAZADA'))->toContain($trazo('x-circle'));
});

it('el tooltip toma la etiqueta personalizada si la vista la pasa', function () {
    $html = Blade::render('<x-ui.badge compact status="PAGADO">Pagado el 20/09</x-ui.badge>');

    expect($html)->toContain('data-tooltip="Pagado el 20/09"')->toContain('is-positivo');
});

it('sin compact el badge sigue mostrando el texto completo', function () {
    $html = Blade::render('<x-ui.badge status="PENDIENTE_ANALISIS">Pendiente de análisis</x-ui.badge>');

    expect($html)->toContain('rounded-full px-2.5')->toContain('Pendiente de análisis')->not->toContain('estado-compacto');
});

it('la tabla de acopio usa el estado compacto y el detalle de la entrega conserva el texto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 25.0);

    $this->actingAs($admin)->get(route('admin.entregas.index'))
        ->assertOk()
        ->assertSee('class="estado-compacto is-pendiente" data-tooltip="Pendiente de análisis"', false);

    $this->get(route('admin.entregas.show', $entrega->id))
        ->assertOk()
        ->assertDontSee('estado-compacto', false);
});
