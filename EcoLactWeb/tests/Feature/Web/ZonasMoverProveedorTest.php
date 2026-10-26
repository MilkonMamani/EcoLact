<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Application\Zona\MoverProveedorDeZona;
use App\Domain\Auditoria\AuditoriaRepository;
use App\Infrastructure\Persistence\Models\AuditoriaModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ProveedorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * "Mover ubicación" del mapa general de Zonas: el servidor recalcula la zona con las
 * delimitaciones guardadas; misma zona = solo coordenadas, otra zona = cambio de ruta y zona.
 * Collana 1, Faon y Collana 2 son cuadrados de 0.1° que no se tocan.
 */

/** Zona con delimitación cuadrada de 0.1° centrada en (lat, lng) y una ruta activa. */
function mover_zona(string $nombre, float $lat, float $lng): ZonaModel
{
    $zona = fixture_zona($nombre);
    $zona->update(['poligono_geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [[
        [$lng - 0.05, $lat + 0.05], [$lng + 0.05, $lat + 0.05], [$lng + 0.05, $lat - 0.05], [$lng - 0.05, $lat - 0.05], [$lng - 0.05, $lat + 0.05],
    ]]])]);
    app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta '.$nombre);

    return $zona;
}

function mover_asignar(ZonaModel $zona, float $lat, float $lng, string $nombres = 'Proveedor'): ProveedorModel
{
    $proveedor = fixture_proveedor($zona);
    $proveedor->update(['nombres' => $nombres, 'apellidos' => 'Mamani']);
    app(AsignarProveedorARuta::class)->execute(
        rutaId: RutaModel::where('zona_id', $zona->id)->value('id'),
        proveedorId: $proveedor->id,
        latitud: $lat,
        longitud: $lng,
        referenciaUbicacion: 'Frente a la capilla',
    );

    return $proveedor;
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'America/Lima'));

    $this->admin = fixture_usuario('ADMINISTRADOR');
    $this->collana1 = mover_zona('Collana 1', -15.85, -70.00);
    $this->faon = mover_zona('Faon', -15.85, -69.80);
    $this->collana2 = mover_zona('Collana 2', -15.65, -70.00);
    $this->milkon = mover_asignar($this->collana1, -15.85, -70.00, 'Milkon');
});

afterEach(fn () => Carbon::setTestNow());

function mover(float $lat, float $lng, array $extra = []): array
{
    return ['latitud' => $lat, 'longitud' => $lng, ...$extra];
}

it('dentro de la misma zona solo actualiza las coordenadas, sin tocar zona, ruta ni su historial', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('admin.zonas.proveedores.ubicacion', $this->milkon->id), mover(-15.83, -70.02))
        ->assertOk()
        ->assertJsonPath('message', 'Ubicación de Milkon Mamani actualizada.')
        ->assertJsonPath('movimiento.cambioZona', false)
        ->assertJsonPath('movimiento.zonaNueva.id', $this->collana1->id);

    $asignacion = RutaDetalleModel::where('proveedor_id', $this->milkon->id)->first();
    expect((float) $asignacion->latitud)->toBe(-15.83)
        ->and((float) $asignacion->longitud)->toBe(-70.02)
        ->and($asignacion->ruta->zona_id)->toBe($this->collana1->id)
        ->and($asignacion->referencia_ubicacion)->toBe('Frente a la capilla')
        ->and($this->milkon->fresh()->zona_id)->toBe($this->collana1->id)
        ->and(ProveedorZonaHistorialModel::where('proveedor_id', $this->milkon->id)->count())->toBe(0);

    expect(AuditoriaModel::sole())
        ->accion->toBe(MoverProveedorDeZona::ACCION_UBICACION)
        ->registro_id->toBe($this->milkon->id)
        ->user_id->toBe($this->admin->id);
});

it('de Collana 1 a Faon cambia zona, ruta y punto, recalcula los contadores y deja auditoría', function () {
    mover_asignar($this->collana1, -15.86, -70.01);
    mover_asignar($this->faon, -15.85, -69.80);

    $respuesta = $this->actingAs($this->admin)
        ->patchJson(route('admin.zonas.proveedores.ubicacion', $this->milkon->id), mover(-15.84, -69.81, ['zona_id' => $this->faon->id]))
        ->assertOk()
        ->assertJsonPath('message', 'Milkon Mamani pasó de Collana 1 a Faon.')
        ->assertJsonPath('movimiento.cambioZona', true)
        ->assertJsonPath('movimiento.zonaAnterior.nombre', 'Collana 1')
        ->assertJsonPath('movimiento.zonaNueva.nombre', 'Faon');

    $contadores = collect($respuesta->json('mapa.zonas'))->pluck('proveedores', 'nombre')->all();
    expect($contadores)->toBe(['Collana 1' => 1, 'Collana 2' => 0, 'Faon' => 2]);
    expect(collect($respuesta->json('mapa.proveedores'))->firstWhere('id', $this->milkon->id))
        ->toMatchArray(['zonaId' => $this->faon->id, 'zonaNombre' => 'Faon', 'lat' => -15.84, 'lng' => -69.81]);

    $asignacion = RutaDetalleModel::where('proveedor_id', $this->milkon->id)->sole();
    expect($asignacion->ruta->zona_id)->toBe($this->faon->id)
        ->and($asignacion->referencia_ubicacion)->toBe('Frente a la capilla')
        ->and($this->milkon->fresh()->zona_id)->toBe($this->faon->id);
    $this->assertDatabaseHas('proveedor_zona_historial', ['proveedor_id' => $this->milkon->id, 'zona_id' => $this->faon->id, 'fecha_fin' => null]);
    $this->assertDatabaseMissing('proveedor_zona_historial', ['proveedor_id' => $this->milkon->id, 'zona_id' => $this->collana1->id, 'fecha_fin' => null]);

    $auditoria = AuditoriaModel::sole();
    expect($auditoria->accion)->toBe('PROVEEDOR_CAMBIO_ZONA')
        ->and($auditoria->tabla_afectada)->toBe('proveedores')
        ->and($auditoria->user_id)->toBe($this->admin->id)
        ->and($auditoria->datos_anteriores)->toMatchArray(['zona_id' => $this->collana1->id, 'zona' => 'Collana 1', 'latitud' => -15.85, 'longitud' => -70.0])
        ->and($auditoria->datos_nuevos)->toMatchArray(['zona_id' => $this->faon->id, 'zona' => 'Faon', 'latitud' => -15.84, 'longitud' => -69.81]);
});

it('si la zona destino tiene varias rutas activas exige elegir una y usa la elegida', function () {
    $segunda = app(RegistrarRuta::class)->execute(zonaId: $this->collana2->id, nombre: 'Ruta Collana 2 Alta');
    $url = route('admin.zonas.proveedores.ubicacion', $this->milkon->id);

    $this->actingAs($this->admin)->patchJson($url, mover(-15.65, -70.00))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Collana 2 tiene varias rutas activas: elige a cuál pasará el proveedor.');

    $this->actingAs($this->admin)->patchJson($url, mover(-15.65, -70.00, ['ruta_id' => $segunda->id()]))
        ->assertOk();

    expect(RutaDetalleModel::where('proveedor_id', $this->milkon->id)->value('ruta_id'))->toBe($segunda->id());
});

it('rechaza la ubicación sin guardar nada', function (Closure $preparar, float $lat, float $lng, array $extra, string $mensaje) {
    $preparar->call($this);

    $this->actingAs($this->admin)
        ->patchJson(route('admin.zonas.proveedores.ubicacion', $this->milkon->id), mover($lat, $lng, $extra))
        ->assertUnprocessable()
        ->assertJsonPath('message', $mensaje);

    $asignacion = RutaDetalleModel::where('proveedor_id', $this->milkon->id)->sole();
    expect((float) $asignacion->latitud)->toBe(-15.85)
        ->and($asignacion->ruta->zona_id)->toBe($this->collana1->id)
        ->and($this->milkon->fresh()->zona_id)->toBe($this->collana1->id)
        ->and(AuditoriaModel::count())->toBe(0);
})->with([
    'fuera de todas las zonas' => [fn () => null, -16.50, -70.00, [], 'La ubicación seleccionada no pertenece a ninguna delimitación operativa.'],
    'zona destino inactiva' => [fn () => $this->faon->update(['estado' => 'INACTIVO']), -15.85, -69.80, [], 'La zona Faon está inactiva: no se pueden mover proveedores a ella.'],
    'zona destino sin rutas activas' => [fn () => RutaModel::where('zona_id', $this->faon->id)->update(['estado' => 'INACTIVO']), -15.85, -69.80, [], 'Faon no tiene rutas activas: crea una ruta en esa zona antes de mover al proveedor.'],
    // El servidor no confía en la zona que detectó el navegador: vuelve a hacer point-in-polygon.
    'zona confirmada distinta a la real' => [fn () => null, -15.84, -69.81, ['zona_id' => 999], 'La ubicación corresponde a Faon, no a la zona confirmada. Revisa el movimiento e inténtalo de nuevo.'],
]);

it('valida las coordenadas enviadas', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('admin.zonas.proveedores.ubicacion', $this->milkon->id), ['longitud' => -70])
        ->assertJsonValidationErrors(['latitud' => 'Indica la nueva latitud.']);
});

it('solo el administrador puede mover proveedores', function () {
    $acopiador = fixture_acopiador($this->collana1);

    $this->actingAs($acopiador->user)
        ->patchJson(route('admin.zonas.proveedores.ubicacion', $this->milkon->id), mover(-15.84, -69.81))
        ->assertForbidden();

    expect($this->milkon->fresh()->zona_id)->toBe($this->collana1->id);
});

it('si falla la auditoría no queda ningún cambio a medias', function () {
    $this->app->bind(AuditoriaRepository::class, fn () => new class implements AuditoriaRepository
    {
        public function registrar(?int $userId, string $accion, string $tablaAfectada, ?int $registroId, ?array $datosAnteriores, ?array $datosNuevos, ?string $motivo, ?string $ip = null, ?string $userAgent = null): void
        {
            throw new RuntimeException('Auditoría no disponible.');
        }
    });

    expect(fn () => app(MoverProveedorDeZona::class)->execute(proveedorId: $this->milkon->id, latitud: -15.84, longitud: -69.81))
        ->toThrow(RuntimeException::class, 'Auditoría no disponible.');

    $asignacion = RutaDetalleModel::where('proveedor_id', $this->milkon->id)->sole();
    expect($asignacion->ruta->zona_id)->toBe($this->collana1->id)
        ->and((float) $asignacion->latitud)->toBe(-15.85)
        ->and($this->milkon->fresh()->zona_id)->toBe($this->collana1->id);
    $this->assertDatabaseCount('proveedor_zona_historial', 0);
    $this->assertDatabaseHas('proveedor_ruta_historial', ['proveedor_id' => $this->milkon->id, 'ruta_id' => $asignacion->ruta_id, 'fecha_fin' => null]);
});

it('tras el cambio las vistas derivadas muestran la nueva zona y las entregas pasadas conservan la suya', function () {
    $acopiadorCollana = fixture_acopiador($this->collana1);
    $acopiadorFaon = fixture_acopiador($this->faon);
    $entregaAntigua = fixture_entrega($this->milkon, $acopiadorCollana, 120, new DateTimeImmutable('2026-09-22 07:00:00'));

    $this->actingAs($this->admin)
        ->patchJson(route('admin.zonas.proveedores.ubicacion', $this->milkon->id), mover(-15.84, -69.81))
        ->assertOk();

    // Historial: la entrega del 22/09 sigue en Collana 1; una nueva ya toma Faon.
    expect($entregaAntigua->fresh()->zona_id)->toBe($this->collana1->id)
        ->and(fixture_entrega($this->milkon->fresh(), $acopiadorFaon, 90)->zona_id)->toBe($this->faon->id)
        ->and(EntregaModel::count())->toBe(2);

    // /admin/proveedores y su panel.
    $this->actingAs($this->admin)->get(route('admin.proveedores.index'))
        ->assertSeeInOrder(['Milkon Mamani', 'Faon']);
    $this->actingAs($this->admin)->get(route('admin.proveedores.panel', $this->milkon->id))
        ->assertSeeInOrder(['Zona asignada', 'Faon', 'Ruta de acopio', 'Ruta Faon', 'Latitud', '-15.840000', 'Longitud', '-69.810000']);

    // Paneles de zona: sale de Collana 1 y entra en Faon.
    $this->actingAs($this->admin)->get(route('admin.zonas.panel', $this->collana1->id))->assertDontSee('Milkon Mamani');
    $this->actingAs($this->admin)->get(route('admin.zonas.panel', $this->faon->id))->assertSee('Milkon Mamani');

    // "Proveedores de su zona" de cada acopiador.
    $this->actingAs($acopiadorFaon->user)->get(route('acopiador.proveedores.index'))->assertSee('Milkon Mamani');
    $this->actingAs($acopiadorCollana->user)->get(route('acopiador.proveedores.index'))->assertDontSee('Milkon Mamani');
});
