<?php

namespace Database\Seeders;

use App\Domain\Zona\Zona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZonaSeeder extends Seeder
{
    /**
     * Las cuatro zonas operativas de EcoLact con su color en el mapa. La
     * delimitación (poligono_geojson) NO se siembra: queda en null
     * ("Delimitación pendiente") hasta que el administrador la dibuje en
     * Editar zona → Editar delimitación. Nunca se generan coordenadas.
     *
     * Es idempotente: una zona existente conserva su estado, su color
     * elegido y su delimitación ya dibujada. "Existente" incluye una variante
     * equivalente del nombre (Zona::normalizarNombre: "Collana1" ya es
     * "Collana 1"), para no volver a crear duplicados; esas variantes se
     * unen con `php artisan zonas:consolidar`.
     *
     * @var array<string, string>
     */
    private const ZONAS = [
        'Collana 1' => '#16a34a',
        'Collana 2' => '#d99a00',
        'Faon' => '#2563eb',
        'Yasin' => '#dc3f4f',
    ];

    public function run(): void
    {
        $existentes = DB::table('zonas')->pluck('nombre')->map(fn (string $nombre): string => Zona::normalizarNombre($nombre));

        foreach (self::ZONAS as $nombre => $color) {
            if (! $existentes->contains(Zona::normalizarNombre($nombre))) {
                DB::table('zonas')->insert([
                    'nombre' => $nombre,
                    'estado' => 'ACTIVO',
                    'color_mapa' => $color,
                    'poligono_geojson' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('zonas')->where('nombre', $nombre)->whereNull('color_mapa')->update(['color_mapa' => $color, 'updated_at' => now()]);
        }
    }
}
