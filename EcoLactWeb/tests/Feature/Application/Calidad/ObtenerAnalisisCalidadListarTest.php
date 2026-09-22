<?php

use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('lista todos los análisis registrados, sin acotar por zona ni proveedor', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);
    $entregaA = fixture_entrega($proveedorA, $acopiadorA, 30.0);
    $entregaB = fixture_entrega($proveedorB, $acopiadorB, 40.0);
    fixture_parametro('GRASA');

    app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregaA->id, valores: ['GRASA' => 3.5]);
    app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregaB->id, valores: ['GRASA' => 3.6]);

    $lista = app(ObtenerAnalisisCalidad::class)->listar();

    expect($lista)->toHaveCount(2);
    $entregaIds = collect($lista)->map(fn ($analisis) => $analisis->entregaId())->all();
    expect($entregaIds)->toEqualCanonicalizing([$entregaA->id, $entregaB->id]);
});
