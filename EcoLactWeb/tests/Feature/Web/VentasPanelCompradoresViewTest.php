<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de compradores muestra los KPIs y permite registrar uno nuevo', function () {
    $ventasUser = fixture_usuario('VENTAS');

    $response = $this->actingAs($ventasUser)->post(route('ventas.compradores.store'), [
        'nombre' => 'Comercial Andina',
        'tipo_cliente' => 'MAYORISTA',
        'documento' => '20456789123',
    ]);

    $response->assertRedirect(route('ventas.compradores.index'));

    $index = $this->actingAs($ventasUser)->get(route('ventas.compradores.index'));
    $index->assertOk();
    $index->assertSee('Comercial Andina');
    $index->assertSee('Mayoristas');
});

it('filtra compradores por tipo de cliente', function () {
    $ventasUser = fixture_usuario('VENTAS');
    $this->actingAs($ventasUser)->post(route('ventas.compradores.store'), ['nombre' => 'Mayorista Uno', 'tipo_cliente' => 'MAYORISTA']);
    $this->actingAs($ventasUser)->post(route('ventas.compradores.store'), ['nombre' => 'Publico Uno', 'tipo_cliente' => 'PUBLICO']);

    $response = $this->actingAs($ventasUser)->get(route('ventas.compradores.index', ['tipo_cliente' => 'MAYORISTA']));

    $response->assertOk();
    $response->assertSee('Mayorista Uno');
    $response->assertDontSee('Publico Uno');
});
