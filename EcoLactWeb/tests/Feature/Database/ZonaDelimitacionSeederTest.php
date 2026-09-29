<?php

use App\Domain\Zona\DelimitacionZona;
use Database\Seeders\ZonaDelimitacionSeeder;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** @return array<string, DelimitacionZona> */
function delimitaciones_guardadas(): array
{
    return DB::table('zonas')->pluck('poligono_geojson', 'nombre')
        ->map(fn (?string $geojson) => DelimitacionZona::desdeGeoJsonONull($geojson))
        ->filter()
        ->all();
}

it('deja las cuatro zonas con una delimitación GeoJSON válida alrededor de Huata', function () {
    $this->seed([ZonaSeeder::class, ZonaDelimitacionSeeder::class]);

    $delimitaciones = delimitaciones_guardadas();

    expect(array_keys($delimitaciones))->toEqualCanonicalizing(['Collana 1', 'Collana 2', 'Faon', 'Yasin']);
    foreach ($delimitaciones as $delimitacion) {
        expect(count($delimitacion->vertices))->toBeGreaterThanOrEqual(8)->toBeLessThanOrEqual(20);
        foreach ($delimitacion->vertices as [$lat, $lng]) {
            expect($lat)->toBeGreaterThan(-15.70)->toBeLessThan(-15.52)
                ->and($lng)->toBeGreaterThan(-70.06)->toBeLessThan(-69.88);
        }
    }
});

it('las zonas son contiguas: cada punto interior del territorio cae en exactamente una zona', function () {
    $this->seed([ZonaSeeder::class, ZonaDelimitacionSeeder::class]);
    $delimitaciones = delimitaciones_guardadas();
    $conteos = [];

    for ($lat = -15.600; $lat >= -15.630; $lat -= 0.0025) {
        for ($lng = -70.020; $lng <= -69.930; $lng += 0.0025) {
            $conteos[] = count(array_filter($delimitaciones, fn (DelimitacionZona $d): bool => $d->contiene($lat, $lng)));
        }
    }

    expect(array_unique($conteos))->toBe([1]);
});

it('respeta la distribución: Collana 2 al norte, Faon al sur, Collana 1 al oeste y Yasin al este', function () {
    $this->seed([ZonaSeeder::class, ZonaDelimitacionSeeder::class]);
    $etiqueta = array_map(fn (DelimitacionZona $d): array => $d->puntoEtiqueta(), delimitaciones_guardadas());

    expect($etiqueta['Collana 2']['lat'])->toBeGreaterThan($etiqueta['Faon']['lat'])
        ->and($etiqueta['Collana 1']['lng'])->toBeLessThan($etiqueta['Yasin']['lng']);
});

it('no pisa una delimitación editada, no cambia un color elegido y no crea zonas', function () {
    $editada = '{"type":"Polygon","coordinates":[[[-70.05,-15.8],[-69.95,-15.8],[-69.95,-15.9],[-70.05,-15.8]]]}';
    DB::table('zonas')->insert([
        ['nombre' => 'Collana 1', 'estado' => 'ACTIVO', 'color_mapa' => '#123456', 'poligono_geojson' => $editada],
        ['nombre' => 'Faon', 'estado' => 'ACTIVO', 'color_mapa' => null, 'poligono_geojson' => null],
    ]);

    $this->seed(ZonaDelimitacionSeeder::class);
    $this->seed(ZonaDelimitacionSeeder::class);

    expect(DB::table('zonas')->count())->toBe(2);
    expect(DB::table('zonas')->where('nombre', 'Collana 1')->first())
        ->poligono_geojson->toBe($editada)
        ->color_mapa->toBe('#123456');
    expect(DB::table('zonas')->where('nombre', 'Faon')->first())
        ->poligono_geojson->not->toBeNull()
        ->color_mapa->toBe('#2563eb');
});
