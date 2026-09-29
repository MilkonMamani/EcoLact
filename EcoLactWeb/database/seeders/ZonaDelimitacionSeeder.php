<?php

namespace Database\Seeders;

use App\Domain\Zona\DelimitacionZona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Delimitaciones operativas referenciales INICIALES de las cuatro zonas de EcoLact alrededor de
 * Huata (Puno). No son límites oficiales, comunales ni catastrales: son un punto de partida para
 * que el mapa no aparezca vacío, y el administrador las ajusta después desde
 * Editar zona → Editar delimitación (esa geometría editada es la operativa final).
 *
 * Distribución: Collana 2 al norte/noroeste, Collana 1 al oeste/suroeste, Faon al sur/sureste
 * y Yasin al este/sureste. Las cuatro parten de un mismo punto central junto a Huata y cada
 * frontera compartida usa EXACTAMENTE los mismos vértices en las dos zonas que separa: son
 * contiguas, sin huecos ni solapes. Las fronteras se trazaron para que los proveedores que ya
 * tienen coordenadas queden dentro de su propia zona.
 *
 * Idempotente y conservador: solo escribe en zonas que existen con ese nombre y que todavía NO
 * tienen geometría (poligono_geojson null); el color solo se completa si está vacío. Nunca crea
 * zonas ni pisa una delimitación dibujada o editada por el administrador.
 */
class ZonaDelimitacionSeeder extends Seeder
{
    /** Punto central compartido por las cuatro zonas, al oeste del núcleo urbano de Huata. */
    private const CENTRO = [-15.6130, -69.9790];

    /** Esquinas exteriores donde terminan las fronteras compartidas. */
    private const NOROESTE = [-15.5720, -70.0300];

    private const NORESTE = [-15.5720, -69.9300];

    private const SURESTE = [-15.6380, -69.9250];

    private const SUROESTE = [-15.6330, -70.0300];

    /**
     * Fronteras interiores, del centro hacia afuera (sin el centro ni la esquina final),
     * en [latitud, longitud].
     *
     * @var array<string, list<array{0: float, 1: float}>>
     */
    private const FRONTERAS = [
        'collana1-collana2' => [[-15.6050, -69.9800], [-15.5960, -69.9820], [-15.5880, -69.9880], [-15.5800, -70.0020], [-15.5750, -70.0180]],
        'collana2-yasin' => [[-15.6080, -69.9720], [-15.6000, -69.9620], [-15.5900, -69.9500], [-15.5800, -69.9380]],
        'yasin-faon' => [[-15.6160, -69.9700], [-15.6200, -69.9580], [-15.6260, -69.9450], [-15.6320, -69.9330]],
        'faon-collana1' => [[-15.6150, -69.9850], [-15.6148, -69.9950], [-15.6190, -70.0080], [-15.6260, -70.0200]],
    ];

    /**
     * Bordes exteriores de cada zona en sentido horario (sin las esquinas), en [latitud, longitud].
     *
     * @var array<string, list<array{0: float, 1: float}>>
     */
    private const BORDES = [
        'Collana 2' => [[-15.5600, -70.0150], [-15.5540, -69.9950], [-15.5520, -69.9700], [-15.5580, -69.9450]],
        'Yasin' => [[-15.5850, -69.9150], [-15.6000, -69.9050], [-15.6180, -69.9020], [-15.6300, -69.9100]],
        'Faon' => [[-15.6500, -69.9350], [-15.6600, -69.9550], [-15.6650, -69.9800], [-15.6600, -70.0050], [-15.6480, -70.0220]],
        'Collana 1' => [[-15.6150, -70.0400], [-15.5950, -70.0420], [-15.5820, -70.0380]],
    ];

    /** @var array<string, string> */
    private const COLORES = [
        'Collana 1' => '#16a34a',
        'Collana 2' => '#d99a00',
        'Faon' => '#2563eb',
        'Yasin' => '#dc3f4f',
    ];

    public function run(): void
    {
        foreach (self::poligonos() as $nombre => $vertices) {
            $geojson = json_encode(DelimitacionZona::desdeVertices($vertices)->aGeoJson());

            DB::table('zonas')->where('nombre', $nombre)->whereNull('poligono_geojson')
                ->update(['poligono_geojson' => $geojson, 'updated_at' => now()]);
            DB::table('zonas')->where('nombre', $nombre)->whereNull('color_mapa')
                ->update(['color_mapa' => self::COLORES[$nombre], 'updated_at' => now()]);
        }
    }

    /**
     * Arma cada anillo con el centro, las fronteras compartidas (una de ida y la otra de vuelta,
     * invertida) y su borde exterior, de modo que dos zonas vecinas usen los mismos vértices.
     *
     * @return array<string, list<array{0: float, 1: float}>>
     */
    public static function poligonos(): array
    {
        $f = self::FRONTERAS;
        $centro = [self::CENTRO];

        return [
            'Collana 1' => [...$centro, ...$f['collana1-collana2'], self::NOROESTE, ...array_reverse(self::BORDES['Collana 1']), self::SUROESTE, ...array_reverse($f['faon-collana1'])],
            'Collana 2' => [...$centro, ...$f['collana2-yasin'], self::NORESTE, ...array_reverse(self::BORDES['Collana 2']), self::NOROESTE, ...array_reverse($f['collana1-collana2'])],
            'Yasin' => [...$centro, ...$f['yasin-faon'], self::SURESTE, ...array_reverse(self::BORDES['Yasin']), self::NORESTE, ...array_reverse($f['collana2-yasin'])],
            'Faon' => [...$centro, ...$f['faon-collana1'], self::SUROESTE, ...array_reverse(self::BORDES['Faon']), self::SURESTE, ...array_reverse($f['yasin-faon'])],
        ];
    }
}
