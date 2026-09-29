<?php

use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AcopiadorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\TrabajadorModel;
use App\Infrastructure\Persistence\Repositories\EloquentAcopiadorRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/** Acopiador en "Collana 1" con su tramo de historial abierto desde el 01/09. */
function acopiador_con_historial(string $estado = 'ACTIVO'): AcopiadorModel
{
    $acopiador = fixture_acopiador(fixture_zona('Collana 1'), '44445555');
    $acopiador->update(['nombres' => 'Luis', 'apellidos' => 'Mamani', 'estado' => $estado]);

    AcopiadorZonaHistorialModel::create([
        'acopiador_id' => $acopiador->id,
        'zona_id' => $acopiador->zona_id,
        'fecha_inicio' => '2026-09-01',
        'fecha_fin' => $estado === 'ACTIVO' ? null : '2026-09-10',
        'motivo' => 'Asignación inicial',
    ]);

    return $acopiador;
}

it('el listado muestra el estado con un switch (verde activo / rojo inactivo) en lugar del badge', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $activo = acopiador_con_historial();
    $inactivo = fixture_acopiador(fixture_zona('Faon'), '66667777');
    $inactivo->update(['estado' => 'INACTIVO']);

    $html = $this->actingAs($admin)->get(route('admin.acopiadores.index'))
        ->assertOk()
        ->assertSee('data-url-desactivar="'.route('admin.acopiadores.desactivar', $activo->id).'"', false)
        ->assertSee('data-url-reactivar="'.route('admin.acopiadores.reactivar', $inactivo->id).'"', false)
        ->getContent();

    expect(preg_match_all('/role="switch" aria-checked="true"/', $html))->toBe(1)
        ->and(preg_match_all('/role="switch" aria-checked="false"/', $html))->toBe(1);

    $tabla = substr($html, strpos($html, 'data-fichas-filas'), strpos($html, '</table>', strpos($html, 'data-fichas-filas')) - strpos($html, 'data-fichas-filas'));
    expect($tabla)->not->toContain('ring-inset');
});

it('el modal de reactivación ofrece solo las zonas activas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    acopiador_con_historial('INACTIVO');
    fixture_zona('Collana 2');
    fixture_zona('Zona Cerrada')->update(['estado' => 'INACTIVO']);

    $html = $this->actingAs($admin)->get(route('admin.acopiadores.index'))
        ->assertSee('Selecciona la zona a la que se reincorpora')
        ->getContent();

    $modal = substr($html, strpos($html, 'id="modal-reactivar-acopiador"'));
    $modal = substr($modal, 0, strpos($modal, '</form>'));
    expect($modal)->toContain('Collana 1')->toContain('Collana 2')->not->toContain('Zona Cerrada');
});

it('un acopiador activo se desactiva desde el switch y conserva su historial de zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = acopiador_con_historial();

    $this->actingAs($admin)
        ->patchJson(route('admin.acopiadores.desactivar', $acopiador->id))
        ->assertOk()
        ->assertJsonPath('acopiador.estado', 'INACTIVO');

    expect($acopiador->fresh()->estado)->toBe('INACTIVO')
        ->and(AcopiadorModel::find($acopiador->id))->not->toBeNull();

    $tramo = AcopiadorZonaHistorialModel::where('acopiador_id', $acopiador->id)->sole();
    expect($tramo->fecha_fin->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
});

it('un acopiador inactivo no se reactiva sin zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = acopiador_con_historial('INACTIVO');

    $this->actingAs($admin)
        ->patchJson(route('admin.acopiadores.reactivar', $acopiador->id), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['zona_id' => 'Selecciona la zona a la que se reincorpora.']);

    expect($acopiador->fresh()->estado)->toBe('INACTIVO');
    expect(AcopiadorZonaHistorialModel::where('acopiador_id', $acopiador->id)->count())->toBe(1);
});

it('elegir la zona reactiva al acopiador, le asigna la zona y abre un tramo nuevo sin tocar el anterior', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = acopiador_con_historial('INACTIVO');
    $collana2 = fixture_zona('Collana 2');

    $this->actingAs($admin)
        ->patchJson(route('admin.acopiadores.reactivar', $acopiador->id), ['zona_id' => $collana2->id])
        ->assertOk()
        ->assertJson(['acopiador' => ['id' => $acopiador->id, 'estado' => 'ACTIVO', 'zonaId' => $collana2->id, 'zonaNombre' => 'Collana 2']]);

    expect($acopiador->fresh())
        ->estado->toBe('ACTIVO')
        ->zona_id->toBe($collana2->id);

    $tramos = AcopiadorZonaHistorialModel::where('acopiador_id', $acopiador->id)->orderBy('id')->get();
    expect($tramos)->toHaveCount(2);
    expect($tramos[0]->zona_id)->toBe($acopiador->zona_id)
        ->and($tramos[0]->fecha_fin->format('Y-m-d'))->toBe('2026-09-10')
        ->and($tramos[0]->motivo)->toBe('Asignación inicial');
    expect($tramos[1]->zona_id)->toBe($collana2->id)
        ->and($tramos[1]->fecha_inicio->format('Y-m-d'))->toBe(now()->format('Y-m-d'))
        ->and($tramos[1]->fecha_fin)->toBeNull();
});

it('el panel refleja el nuevo estado y la zona tras reactivar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = acopiador_con_historial('INACTIVO');
    $faon = fixture_zona('Faon');

    $this->actingAs($admin)->patchJson(route('admin.acopiadores.reactivar', $acopiador->id), ['zona_id' => $faon->id])->assertOk();

    $this->get(route('admin.acopiadores.panel', $acopiador->id))
        ->assertOk()
        ->assertSeeInOrder(['Activo', 'Luis Mamani'])
        ->assertSeeInOrder(['Zona actual', 'Faon'])
        ->assertSeeInOrder(['Faon', now()->format('d/m/Y'), 'Actual', 'Reactivación del acopiador.']);
});

it('una zona inexistente o inactiva no reactiva', function (string $caso) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = acopiador_con_historial('INACTIVO');
    $zonaId = $caso === 'inexistente' ? 9999 : tap(fixture_zona('Cerrada'))->update(['estado' => 'INACTIVO'])->id;

    $this->actingAs($admin)
        ->patchJson(route('admin.acopiadores.reactivar', $acopiador->id), ['zona_id' => $zonaId])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('zona_id');

    expect($acopiador->fresh()->estado)->toBe('INACTIVO');
})->with(['inexistente', 'inactiva']);

it('reactivar a un acopiador que ya está activo responde 422 con el mensaje de negocio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = acopiador_con_historial();

    $this->actingAs($admin)
        ->patchJson(route('admin.acopiadores.reactivar', $acopiador->id), ['zona_id' => $acopiador->zona_id])
        ->assertUnprocessable()
        ->assertJson(['message' => 'El acopiador ya se encuentra activo.']);

    expect(AcopiadorZonaHistorialModel::where('acopiador_id', $acopiador->id)->count())->toBe(1);
});

it('solo un administrador puede desactivar o reactivar', function () {
    $acopiador = acopiador_con_historial();
    $otroAcopiador = fixture_acopiador(fixture_zona('Faon'));
    $proveedor = fixture_proveedor(fixture_zona('Otra'));

    foreach ([$otroAcopiador->user, $proveedor->user] as $usuario) {
        $this->actingAs($usuario)->patchJson(route('admin.acopiadores.desactivar', $acopiador->id))->assertForbidden();
        $this->actingAs($usuario)->patchJson(route('admin.acopiadores.reactivar', $acopiador->id), ['zona_id' => $acopiador->zona_id])->assertForbidden();
    }

    auth()->logout();
    $this->patchJson(route('admin.acopiadores.desactivar', $acopiador->id))->assertUnauthorized();

    expect($acopiador->fresh()->estado)->toBe('ACTIVO');
});

it('si la reactivación falla a mitad de la transacción no queda ni activo sin zona ni tramo huérfano', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = acopiador_con_historial('INACTIVO');
    $collana2 = fixture_zona('Collana 2');

    // El historial ya se escribió cuando falla la actualización del acopiador.
    $this->app->bind(AcopiadorRepository::class, fn () => new class(new EloquentAcopiadorRepository) implements AcopiadorRepository
    {
        public function __construct(private EloquentAcopiadorRepository $real) {}

        public function guardar(Acopiador $acopiador): Acopiador
        {
            return $this->real->guardar($acopiador);
        }

        public function actualizar(Acopiador $acopiador): Acopiador
        {
            throw new RuntimeException('Fallo simulado al guardar el acopiador.');
        }

        public function buscarPorId(int $id): ?Acopiador
        {
            return $this->real->buscarPorId($id);
        }

        public function buscarPorDni(string $dni): ?Acopiador
        {
            return $this->real->buscarPorDni($dni);
        }

        public function buscarPorUserId(int $userId): ?Acopiador
        {
            return $this->real->buscarPorUserId($userId);
        }

        public function listarPorZona(int $zonaId): array
        {
            return $this->real->listarPorZona($zonaId);
        }

        public function listar(): array
        {
            return $this->real->listar();
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin)->patchJson(route('admin.acopiadores.reactivar', $acopiador->id), ['zona_id' => $collana2->id]))
        ->toThrow(RuntimeException::class);

    expect($acopiador->fresh())->estado->toBe('INACTIVO')->zona_id->toBe($acopiador->zona_id);
    $tramos = AcopiadorZonaHistorialModel::where('acopiador_id', $acopiador->id)->get();
    expect($tramos)->toHaveCount(1)
        ->and($tramos[0]->fecha_fin->format('Y-m-d'))->toBe('2026-09-10');
});

it('el QR del acopiador es su token real de identidad laboral y aparece una sola vez, en Resumen', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $acopiador = acopiador_con_historial();
    $token = 'token-asistencia-luis';
    TrabajadorModel::updateOrCreate(['user_id' => $acopiador->user_id], [
        'dni' => $acopiador->dni, 'nombres' => 'Luis', 'apellidos' => 'Mamani', 'estado' => 'ACTIVO',
        'fecha_ingreso' => '2026-09-01', 'qr_token' => $token,
    ]);

    $html = $this->actingAs($admin)->get(route('admin.acopiadores.panel', $acopiador->id))->assertOk()->getContent();

    expect(substr_count($html, 'data-qr-code='))->toBe(1)
        ->and($html)->toContain('data-qr-code="'.base64_encode($token).'"')
        ->and(strpos($html, 'data-qr-code='))->toBeLessThan(strpos($html, 'data-fichas-tab-panel="entregas"'));
});
