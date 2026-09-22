<?php

use App\Application\Acopio\RegistrarEntrega;
use App\Application\Produccion\ObtenerStockLeche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la leche disponible para producción es el stock del insumo LECHE, alimentado por las entregas sin asignarlas a lotes', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    expect(app(ObtenerStockLeche::class)->execute()->stockActual())->toBe(0.0);

    foreach ([30.0, 20.0] as $litros) {
        app(RegistrarEntrega::class)->execute(
            metodoIdentificacion: 'DNI',
            identificador: $proveedor->dni,
            acopiadorUserId: $acopiador->user_id,
            litros: $litros,
        );
    }

    expect(app(ObtenerStockLeche::class)->execute()->stockActual())->toBe(50.0);
});
