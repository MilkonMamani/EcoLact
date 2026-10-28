<?php

use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('crea las cuatro zonas operativas con su color y la delimitación pendiente', function () {
    $this->seed(ZonaSeeder::class);

    expect(DB::table('zonas')->orderBy('nombre')->get(['nombre', 'estado', 'color_mapa', 'poligono_geojson'])->map(fn ($zona) => (array) $zona)->all())->toBe([
        ['nombre' => 'Collana 1', 'estado' => 'ACTIVO', 'color_mapa' => '#16a34a', 'poligono_geojson' => null],
        ['nombre' => 'Collana 2', 'estado' => 'ACTIVO', 'color_mapa' => '#d99a00', 'poligono_geojson' => null],
        ['nombre' => 'Faon', 'estado' => 'ACTIVO', 'color_mapa' => '#2563eb', 'poligono_geojson' => null],
        ['nombre' => 'Yasin', 'estado' => 'ACTIVO', 'color_mapa' => '#dc3f4f', 'poligono_geojson' => null],
    ]);
});

it('volver a ejecutarlo no pisa una delimitación ya dibujada ni el color elegido', function () {
    $poligono = '{"type":"Polygon","coordinates":[[[-70.05,-15.8],[-69.95,-15.8],[-69.95,-15.9],[-70.05,-15.8]]]}';
    DB::table('zonas')->insert(['nombre' => 'Faon', 'estado' => 'ACTIVO', 'color_mapa' => '#123456', 'poligono_geojson' => $poligono]);

    $this->seed(ZonaSeeder::class);

    expect(DB::table('zonas')->where('nombre', 'Faon')->first())
        ->color_mapa->toBe('#123456')
        ->poligono_geojson->toBe($poligono);
    expect(DB::table('zonas')->count())->toBe(4);
});

it('es idempotente y no vuelve a crear una zona que ya existe con un nombre equivalente', function () {
    DB::table('zonas')->insert(['nombre' => 'Collana1', 'estado' => 'ACTIVO']);

    $this->seed(ZonaSeeder::class);
    $this->seed(ZonaSeeder::class);

    expect(DB::table('zonas')->orderBy('nombre')->pluck('nombre')->all())->toBe(['Collana 2', 'Collana1', 'Faon', 'Yasin']);
});
