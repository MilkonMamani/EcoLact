<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Application\Zona\ZonasMapaData;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * Mapa general de /admin/zonas: delimitaciones operativas (GeoJSON en zonas.poligono_geojson),
 * color por zona, etiqueta con la cantidad de proveedores y un punto por proveedor con coordenadas.
 */

function mapa_asignar(ZonaModel $zona, ?float $lat = null, ?float $lng = null): ProveedorModel
{
    $ruta = RutaModel::where('zona_id', $zona->id)->first()
        ?? RutaModel::find(app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta '.$zona->nombre)->id());
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id, proveedorId: $proveedor->id, latitud: $lat, longitud: $lng);

    return $proveedor;
}

/** Cuadrado de 0.1° alrededor de (-15.85, -70.00), como GeoJSON [longitud, latitud]. */
function mapa_cuadrado(): string
{
    return json_encode(['type' => 'Polygon', 'coordinates' => [[[-70.05, -15.80], [-69.95, -15.80], [-69.95, -15.90], [-70.05, -15.90], [-70.05, -15.80]]]]);
}

beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'America/Lima')));

afterEach(fn () => Carbon::setTestNow());

it('ordena la pantalla como aviso de proveedores sin zona, buscador de proveedores, mapa y panel, sin la tabla "Resumen de zonas"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    fixture_proveedor($zona);

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertSeeInOrder(['Proveedores sin zona', 'Buscar proveedor por nombre o DNI...', 'data-zonas-mapa-general', 'Delimitación operativa referencial', 'data-fichas-panel'], false)
        ->assertDontSee('Resumen de zonas')
        ->assertDontSee('data-fichas-tabla', false)
        ->assertDontSee('Buscar zona por nombre o descripción');
});

it('el mapa trae cada zona con su color, proveedores contados en el servidor y solo los puntos con coordenadas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $collana = fixture_zona('Collana 1');
    $collana->update(['color_mapa' => '#16A34A']);
    $yasin = fixture_zona('Yasin');
    $conPunto = mapa_asignar($collana, -15.85, -70.0);
    mapa_asignar($collana);

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertViewHas('mapaZonas', function (ZonasMapaData $mapa) use ($collana, $yasin, $conPunto): bool {
            expect($mapa->zonas)->toHaveCount(2)
                ->and($mapa->zonas[0])->toMatchArray(['id' => $collana->id, 'color' => '#16a34a', 'geometry' => null, 'etiqueta' => null, 'proveedores' => 2, 'proveedoresConCoordenadas' => 1])
                ->and($mapa->zonas[1])->toMatchArray(['id' => $yasin->id, 'proveedores' => 0, 'proveedoresConCoordenadas' => 0])
                ->and($mapa->zonas[1]['color'])->toMatch('/^#[0-9a-f]{6}$/');
            expect($mapa->proveedores)->toHaveCount(1)
                ->and($mapa->proveedores[0])->toMatchArray(['id' => $conPunto->id, 'zonaId' => $collana->id, 'zonaNombre' => 'Collana 1', 'lat' => -15.85, 'lng' => -70.0, 'fueraDeDelimitacion' => false]);

            return true;
        })
        ->assertSee('Todavía no hay delimitaciones dibujadas');
});

it('con delimitación guardada el mapa trae la geometría, el punto de la etiqueta y marca proveedores fuera de ella', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $zona->update(['poligono_geojson' => mapa_cuadrado()]);
    mapa_asignar($zona, -15.85, -70.0);
    $fuera = mapa_asignar($zona, -16.20, -70.0);

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertViewHas('mapaZonas', function (ZonasMapaData $mapa) use ($fuera): bool {
            expect($mapa->zonas[0]['geometry']['type'])->toBe('Polygon')
                ->and($mapa->zonas[0]['etiqueta']['lat'])->toEqualWithDelta(-15.85, 1e-9)
                ->and($mapa->zonas[0]['etiqueta']['lng'])->toEqualWithDelta(-70.0, 1e-9);
            expect(collect($mapa->proveedores)->where('fueraDeDelimitacion', true)->pluck('id')->all())->toBe([$fuera->id]);

            return true;
        })
        ->assertDontSee('Todavía no hay delimitaciones dibujadas');
});

it('el mapa muestra todas las zonas, activas e inactivas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_zona('Collana 1');
    fixture_zona('Faon')->update(['estado' => 'INACTIVO']);

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertViewHas('mapaZonas', fn (ZonasMapaData $mapa): bool => array_column($mapa->zonas, 'nombre') === ['Collana 1', 'Faon']);
});

it('el panel cuenta proveedores con y sin coordenadas y avisa de ubicaciones fuera de la delimitación, sin mini mapa propio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $zona->update(['poligono_geojson' => mapa_cuadrado()]);
    $otra = fixture_zona('Faon');
    mapa_asignar($zona, -15.85, -70.0);
    $fuera = mapa_asignar($zona, -16.20, -70.0);
    $fuera->update(['nombres' => 'Rosa', 'apellidos' => 'Mamani']);
    mapa_asignar($zona);
    $ajeno = mapa_asignar($otra, -15.5, -70.1);

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertSeeInOrder(['Delimitación y ubicaciones', 'Con coordenadas', '2', 'Sin coordenadas', '1'])
        ->assertSee('Rosa Mamani:</strong> la ubicación registrada se encuentra fuera de la delimitación operativa de Collana 1.', false)
        ->assertDontSee('Ubicación de proveedores')
        ->assertDontSee('data-zona-mapa-datos', false)
        ->assertDontSee('Esta zona aún no tiene una delimitación operativa.')
        ->assertDontSee($ajeno->dni);
});

it('una zona sin proveedores y sin delimitación muestra el panel sin mapa', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Yasin');

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertSee('Esta zona aún no tiene una delimitación operativa.')
        ->assertSee('Esta zona aún no tiene proveedores.')
        ->assertDontSee('data-zona-mapa-datos', false)
        ->assertSee('Delimitación pendiente')
        ->assertSee('Dibujar delimitación');
});

it('editar delimitación guarda el color y el polígono como GeoJSON cerrado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $abierto = json_encode(['type' => 'Polygon', 'coordinates' => [[[-70.05, -15.80], [-69.95, -15.80], [-69.95, -15.90], [-70.05, -15.90]]]]);

    $this->actingAs($admin)->patch(route('admin.zonas.update', $zona->id), ['nombre' => 'Collana 1', 'color_mapa' => '#2563EB', 'poligono_geojson' => $abierto])
        ->assertRedirect(route('admin.zonas.index', ['zona' => $zona->id]))
        ->assertSessionHas('success', 'Zona actualizada correctamente.');

    $zona->refresh();
    expect($zona->color_mapa)->toBe('#2563eb')
        ->and(json_decode($zona->poligono_geojson, true))->toBe(json_decode(mapa_cuadrado(), true));
});

it('guardar la zona sin abrir el editor no toca la delimitación, y enviarla vacía la quita', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $zona->update(['color_mapa' => '#16a34a', 'poligono_geojson' => mapa_cuadrado()]);

    $this->actingAs($admin)->patch(route('admin.zonas.update', $zona->id), ['nombre' => 'Collana Uno']);

    expect($zona->fresh()->poligono_geojson)->not->toBeNull();

    $this->patch(route('admin.zonas.update', $zona->id), ['color_mapa' => '#16a34a', 'poligono_geojson' => '']);

    expect($zona->fresh()->poligono_geojson)->toBeNull();
});

it('rechaza una delimitación inválida sin modificar la zona', function (array $datos, string $campo, string $mensaje) {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $zona->update(['color_mapa' => '#16a34a', 'poligono_geojson' => mapa_cuadrado()]);

    $this->actingAs($admin)->patch(route('admin.zonas.update', $zona->id), [...['nombre' => 'Otro nombre', 'color_mapa' => '#dc2626'], ...$datos])
        ->assertSessionHasErrors([$campo => $mensaje]);

    $zona->refresh();
    expect($zona->nombre)->toBe('Collana 1')
        ->and($zona->color_mapa)->toBe('#16a34a')
        ->and($zona->poligono_geojson)->toBe(mapa_cuadrado());
})->with([
    'dos puntos' => [['poligono_geojson' => '{"type":"Polygon","coordinates":[[[-70.05,-15.8],[-69.95,-15.8],[-70.05,-15.8]]]}'], 'poligono_geojson', 'La delimitación necesita al menos 3 puntos distintos.'],
    'lados cruzados' => [['poligono_geojson' => '{"type":"Polygon","coordinates":[[[-70.05,-15.8],[-69.95,-15.9],[-69.95,-15.8],[-70.05,-15.9],[-70.05,-15.8]]]}'], 'poligono_geojson', 'La delimitación no es válida: sus lados se cruzan entre sí.'],
    'json roto' => [['poligono_geojson' => '{"type":'], 'poligono_geojson', 'La delimitación no es un JSON válido.'],
    'color' => [['color_mapa' => 'verde'], 'color_mapa', 'El color del mapa debe tener el formato #RRGGBB.'],
]);

/** Cuadrado de 0.1° con esquina noroeste en ($lat, $lng), como GeoJSON [longitud, latitud]. */
function mapa_cuadrado_en(float $lat, float $lng): string
{
    return json_encode(['type' => 'Polygon', 'coordinates' => [[[$lng, $lat], [$lng + 0.1, $lat], [$lng + 0.1, $lat - 0.1], [$lng, $lat - 0.1], [$lng, $lat]]]]);
}

it('las cuatro zonas con delimitación se entregan juntas al mapa, cada una con su propio contador', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonas = [
        'Collana 1' => [-15.60, -70.10],
        'Collana 2' => [-15.50, -70.00],
        'Faon' => [-15.70, -70.00],
        'Yasin' => [-15.60, -69.90],
    ];
    foreach ($zonas as $nombre => [$lat, $lng]) {
        fixture_zona($nombre)->update(['poligono_geojson' => mapa_cuadrado_en($lat, $lng)]);
    }
    $collana1 = ZonaModel::where('nombre', 'Collana 1')->first();
    mapa_asignar($collana1, -15.65, -70.05);
    mapa_asignar($collana1);
    mapa_asignar($collana1);

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertViewHas('mapaZonas', function (ZonasMapaData $mapa): bool {
            expect(array_column($mapa->zonas, 'nombre'))->toBe(['Collana 1', 'Collana 2', 'Faon', 'Yasin'])
                ->and(array_filter(array_column($mapa->zonas, 'geometry')))->toHaveCount(4)
                ->and(array_column($mapa->zonas, 'proveedores'))->toBe([3, 0, 0, 0])
                ->and(array_column($mapa->zonas, 'proveedoresConCoordenadas'))->toBe([1, 0, 0, 0])
                ->and($mapa->toArray()['tipoDelimitacion'])->toBe('OPERATIVA_REFERENCIAL');
            expect($mapa->proveedores)->toHaveCount(1);

            return true;
        })
        ->assertDontSee('Delimitación pendiente')
        ->assertDontSee('Todavía no hay delimitaciones dibujadas');
});

it('indica qué zonas tienen la delimitación pendiente cuando solo algunas están dibujadas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_zona('Collana 1')->update(['poligono_geojson' => mapa_cuadrado()]);
    fixture_zona('Faon');
    fixture_zona('Yasin');

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertSee('Delimitación pendiente: Faon, Yasin.');
});

it('volver a guardar la delimitación reemplaza el polígono anterior', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $zona->update(['color_mapa' => '#16a34a', 'poligono_geojson' => mapa_cuadrado()]);
    $ajustado = mapa_cuadrado_en(-15.60, -70.10);

    $this->actingAs($admin)->patch(route('admin.zonas.update', $zona->id), ['color_mapa' => '#16a34a', 'poligono_geojson' => $ajustado]);

    expect(json_decode($zona->fresh()->poligono_geojson, true))->toBe(json_decode($ajustado, true));
});
