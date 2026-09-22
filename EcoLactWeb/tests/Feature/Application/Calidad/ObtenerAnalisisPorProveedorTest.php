<?php

use App\Application\Calidad\ObtenerAnalisisPorProveedor;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

it('lista solo los análisis de las entregas del proveedor indicado', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona, '11112222');
    $proveedorB = fixture_proveedor($zona, '33334444');
    $acopiador = fixture_acopiador($zona);
    fixture_parametro('GRASA');

    $entregaA = fixture_entrega($proveedorA, $acopiador, 30.0);
    $entregaB = fixture_entrega($proveedorB, $acopiador, 20.0);

    app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregaA->id, valores: ['GRASA' => 3.5]);
    app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entregaB->id, valores: ['GRASA' => 3.5]);

    $analisis = app(ObtenerAnalisisPorProveedor::class)->execute($proveedorA->id);

    expect($analisis)->toHaveCount(1);
    expect($analisis[0]->entregaId())->toBe($entregaA->id);
});
