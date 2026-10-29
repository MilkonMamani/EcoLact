<?php

use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra un único saldo inicial como entrada y la caja lo muestra como saldo disponible', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->artisan('caja:saldo-inicial', ['monto' => 5000, '--usuario' => $admin->username])->assertSuccessful();
    $this->artisan('caja:saldo-inicial', ['monto' => 9999, '--usuario' => $admin->username])
        ->expectsOutputToContain('ya tiene saldo inicial de S/ 5,000.00')
        ->assertSuccessful();

    $movimiento = MovimientoCajaModel::sole();
    expect($movimiento->tipo)->toBe('ENTRADA');
    expect($movimiento->categoria)->toBe('SALDO_INICIAL');
    expect((float) $movimiento->monto)->toBe(5000.0);
    expect($movimiento->registrado_por_user_id)->toBe($admin->id);

    $this->actingAs($admin)->get(route('admin.caja.index'))->assertOk()->assertSee('5,000.00')->assertSee('Saldo inicial de caja');
});

it('no registra saldo inicial con monto inválido ni con un usuario que no es administrador', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->artisan('caja:saldo-inicial', ['monto' => 5000, '--usuario' => $encargado->username])->assertFailed();
    $this->artisan('caja:saldo-inicial', ['monto' => 0, '--usuario' => $admin->username])->assertFailed();

    expect(MovimientoCajaModel::count())->toBe(0);
});
