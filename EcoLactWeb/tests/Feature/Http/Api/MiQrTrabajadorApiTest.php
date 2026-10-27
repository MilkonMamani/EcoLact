<?php

use App\Infrastructure\Persistence\Models\JornadaPersonalModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function trabajadorDe(User $user, string $qr): TrabajadorModel
{
    return TrabajadorModel::create([
        'user_id' => $user->id,
        'dni' => fixture_next_dni(),
        'nombres' => 'María',
        'apellidos' => 'Torres',
        'estado' => 'ACTIVO',
        'fecha_ingreso' => now()->subMonth(),
        'qr_token' => $qr,
    ]);
}

it('el analista de calidad recibe su identidad de trabajador y su QR para mostrarlo sin conexión', function () {
    $analista = fixture_usuario('CALIDAD');
    $trabajador = trabajadorDe($analista, 'qr-calidad-1');

    $this->actingAs($analista, 'sanctum')->getJson(route('api.calidad.mi-asistencia'))
        ->assertOk()
        ->assertJsonPath('data.registrado', true)
        ->assertJsonPath('data.trabajador_id', $trabajador->id)
        ->assertJsonPath('data.user_id', $analista->id)
        ->assertJsonPath('data.nombres', 'María')
        ->assertJsonPath('data.apellidos', 'Torres')
        ->assertJsonPath('data.dni', $trabajador->dni)
        ->assertJsonPath('data.qr_token', 'qr-calidad-1');
});

it('el acopiador recibe el mismo QR único de su ficha de trabajador', function () {
    $acopiador = fixture_acopiador(fixture_zona());
    trabajadorDe($acopiador->user, 'qr-acopiador-1');

    $this->actingAs($acopiador->user, 'sanctum')->getJson(route('api.acopiador.mi-asistencia'))
        ->assertOk()
        ->assertJsonPath('data.qr_token', 'qr-acopiador-1')
        ->assertJsonPath('data.nombres', 'María');
});

it('ni el acopiador ni el analista pueden registrar su propia jornada con su QR', function () {
    $analista = fixture_usuario('CALIDAD');
    trabajadorDe($analista, 'qr-calidad-2');
    $acopiador = fixture_acopiador(fixture_zona());
    trabajadorDe($acopiador->user, 'qr-acopiador-2');

    $this->actingAs($analista, 'sanctum')->postJson(route('api.admin.asistencia.qr'), ['qr_token' => 'qr-calidad-2'])->assertForbidden();
    $this->actingAs($acopiador->user, 'sanctum')->postJson(route('api.admin.asistencia.qr'), ['qr_token' => 'qr-acopiador-2'])->assertForbidden();

    expect(JornadaPersonalModel::count())->toBe(0);
});
