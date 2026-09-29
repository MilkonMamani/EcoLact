<?php

namespace App\Domain\Zona;

use InvalidArgumentException;
use JsonException;

/**
 * Delimitación operativa de acopio de una zona: un polígono simple dibujado a
 * mano por el administrador (no es un límite político ni catastral oficial).
 *
 * Se persiste como geometría GeoJSON "Polygon" con un único anillo cerrado
 * en orden [longitud, latitud]; internamente los vértices se guardan como
 * [latitud, longitud] sin repetir el primero al final.
 */
final readonly class DelimitacionZona
{
    /** No es un límite oficial, político ni catastral: es referencial para la operación de acopio. */
    public const TIPO = 'OPERATIVA_REFERENCIAL';

    public const MAX_VERTICES = 500;

    private const EPSILON = 1e-12;

    /**
     * @param  list<array{0: float, 1: float}>  $vertices  [latitud, longitud]
     */
    private function __construct(public array $vertices) {}

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $vertices  [latitud, longitud]; el anillo puede venir cerrado o abierto
     */
    public static function desdeVertices(array $vertices): self
    {
        $limpios = [];

        foreach ($vertices as $vertice) {
            if (! is_array($vertice) || count($vertice) < 2 || ! is_numeric($vertice[0] ?? null) || ! is_numeric($vertice[1] ?? null)) {
                throw new InvalidArgumentException('Cada punto de la delimitación debe tener latitud y longitud numéricas.');
            }

            $lat = (float) $vertice[0];
            $lng = (float) $vertice[1];

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new InvalidArgumentException('La delimitación tiene un punto con coordenadas fuera de rango.');
            }

            $anterior = $limpios[array_key_last($limpios) ?? 0] ?? null;

            if ($anterior === null || ! self::mismoPunto($anterior, [$lat, $lng])) {
                $limpios[] = [$lat, $lng];
            }
        }

        // El anillo se cierra solo: si el último punto repite el primero, se descarta.
        if (count($limpios) > 1 && self::mismoPunto($limpios[0], $limpios[array_key_last($limpios)])) {
            array_pop($limpios);
        }

        if (count($limpios) < 3) {
            throw new InvalidArgumentException('La delimitación necesita al menos 3 puntos distintos.');
        }

        if (count($limpios) > self::MAX_VERTICES) {
            throw new InvalidArgumentException('La delimitación no puede tener más de '.self::MAX_VERTICES.' puntos.');
        }

        $delimitacion = new self($limpios);

        if ($delimitacion->seAutointersecta()) {
            throw new InvalidArgumentException('La delimitación no es válida: sus lados se cruzan entre sí.');
        }

        if (abs($delimitacion->areaConSigno()) < self::EPSILON) {
            throw new InvalidArgumentException('Los puntos de la delimitación no pueden estar todos en línea recta.');
        }

        return $delimitacion;
    }

    /**
     * Acepta la geometría GeoJSON (texto o arreglo decodificado) de un
     * "Polygon", o un "Feature" que la contenga. Solo se admite el anillo
     * exterior: una delimitación operativa no tiene huecos.
     *
     * @param  string|array<string, mixed>  $geojson
     */
    public static function desdeGeoJson(string|array $geojson): self
    {
        if (is_string($geojson)) {
            try {
                $geojson = json_decode($geojson, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException('La delimitación no es un JSON válido.');
            }
        }

        if (is_array($geojson) && ($geojson['type'] ?? null) === 'Feature') {
            $geojson = $geojson['geometry'] ?? null;
        }

        if (! is_array($geojson) || ($geojson['type'] ?? null) !== 'Polygon' || ! is_array($geojson['coordinates'] ?? null)) {
            throw new InvalidArgumentException('La delimitación debe ser una geometría GeoJSON de tipo Polygon.');
        }

        $anillos = $geojson['coordinates'];

        if (count($anillos) !== 1 || ! is_array($anillos[0] ?? null)) {
            throw new InvalidArgumentException('La delimitación debe tener un único contorno, sin huecos.');
        }

        return self::desdeVertices(array_map(
            fn (mixed $punto): array => is_array($punto) ? [$punto[1] ?? null, $punto[0] ?? null] : [null, null],
            array_values($anillos[0]),
        ));
    }

    /**
     * Lectura tolerante de lo guardado en zonas.poligono_geojson: null o una geometría que ya
     * no se pueda leer dejan la zona sin delimitación ("Delimitación pendiente") en vez de
     * romper la pantalla.
     */
    public static function desdeGeoJsonONull(?string $geojson): ?self
    {
        if ($geojson === null || trim($geojson) === '') {
            return null;
        }

        try {
            return self::desdeGeoJson($geojson);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Geometría GeoJSON con el anillo cerrado y en orden [longitud, latitud].
     *
     * @return array{type: string, coordinates: list<list<array{0: float, 1: float}>>}
     */
    public function aGeoJson(): array
    {
        $anillo = array_map(fn (array $v): array => [$v[1], $v[0]], $this->vertices);
        $anillo[] = $anillo[0];

        return ['type' => 'Polygon', 'coordinates' => [$anillo]];
    }

    /** Point-in-polygon por cruce de rayos (x = longitud, y = latitud). */
    public function contiene(float $latitud, float $longitud): bool
    {
        $dentro = false;
        $total = count($this->vertices);

        for ($i = 0, $j = $total - 1; $i < $total; $j = $i++) {
            [$yi, $xi] = $this->vertices[$i];
            [$yj, $xj] = $this->vertices[$j];

            if (($yi > $latitud) !== ($yj > $latitud)
                && $longitud < ($xj - $xi) * ($latitud - $yi) / ($yj - $yi) + $xi) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /**
     * Punto donde se dibuja la etiqueta de la zona: el centroide si cae dentro
     * del polígono; si no (polígono cóncavo), el centro del tramo interior
     * más ancho sobre la horizontal que pasa por el centroide.
     *
     * @return array{lat: float, lng: float}
     */
    public function puntoEtiqueta(): array
    {
        $area = $this->areaConSigno();
        $cx = 0.0;
        $cy = 0.0;
        $total = count($this->vertices);

        for ($i = 0; $i < $total; $i++) {
            [$y1, $x1] = $this->vertices[$i];
            [$y2, $x2] = $this->vertices[($i + 1) % $total];
            $cruce = $x1 * $y2 - $x2 * $y1;
            $cx += ($x1 + $x2) * $cruce;
            $cy += ($y1 + $y2) * $cruce;
        }

        $centroide = ['lat' => $cy / (6 * $area), 'lng' => $cx / (6 * $area)];

        if ($this->contiene($centroide['lat'], $centroide['lng'])) {
            return $centroide;
        }

        $cortes = [];

        for ($i = 0, $j = $total - 1; $i < $total; $j = $i++) {
            [$yi, $xi] = $this->vertices[$i];
            [$yj, $xj] = $this->vertices[$j];

            if (($yi > $centroide['lat']) !== ($yj > $centroide['lat'])) {
                $cortes[] = ($xj - $xi) * ($centroide['lat'] - $yi) / ($yj - $yi) + $xi;
            }
        }

        sort($cortes);
        $mejor = null;

        for ($k = 0; $k + 1 < count($cortes); $k += 2) {
            if ($mejor === null || $cortes[$k + 1] - $cortes[$k] > $mejor[1] - $mejor[0]) {
                $mejor = [$cortes[$k], $cortes[$k + 1]];
            }
        }

        return $mejor !== null
            ? ['lat' => $centroide['lat'], 'lng' => ($mejor[0] + $mejor[1]) / 2]
            : ['lat' => array_sum(array_column($this->vertices, 0)) / $total, 'lng' => array_sum(array_column($this->vertices, 1)) / $total];
    }

    /** Fórmula del área de Gauss (shoelace) en grados², con signo según el sentido del anillo. */
    private function areaConSigno(): float
    {
        $suma = 0.0;
        $total = count($this->vertices);

        for ($i = 0; $i < $total; $i++) {
            [$y1, $x1] = $this->vertices[$i];
            [$y2, $x2] = $this->vertices[($i + 1) % $total];
            $suma += $x1 * $y2 - $x2 * $y1;
        }

        return $suma / 2;
    }

    /** Un polígono válido es simple: ningún par de lados no contiguos se toca. */
    private function seAutointersecta(): bool
    {
        $total = count($this->vertices);

        for ($i = 0; $i < $total; $i++) {
            $a1 = $this->vertices[$i];
            $a2 = $this->vertices[($i + 1) % $total];

            for ($j = $i + 1; $j < $total; $j++) {
                $contiguos = $j === $i + 1 || ($i === 0 && $j === $total - 1);

                if ($contiguos) {
                    continue;
                }

                if (self::segmentosSeTocan($a1, $a2, $this->vertices[$j], $this->vertices[($j + 1) % $total])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array{0: float, 1: float}  $p1
     * @param  array{0: float, 1: float}  $p2
     * @param  array{0: float, 1: float}  $q1
     * @param  array{0: float, 1: float}  $q2
     */
    private static function segmentosSeTocan(array $p1, array $p2, array $q1, array $q2): bool
    {
        $o1 = self::orientacion($p1, $p2, $q1);
        $o2 = self::orientacion($p1, $p2, $q2);
        $o3 = self::orientacion($q1, $q2, $p1);
        $o4 = self::orientacion($q1, $q2, $p2);

        if ($o1 !== $o2 && $o3 !== $o4) {
            return true;
        }

        return ($o1 === 0 && self::enSegmento($p1, $q1, $p2))
            || ($o2 === 0 && self::enSegmento($p1, $q2, $p2))
            || ($o3 === 0 && self::enSegmento($q1, $p1, $q2))
            || ($o4 === 0 && self::enSegmento($q1, $p2, $q2));
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @param  array{0: float, 1: float}  $c
     */
    private static function orientacion(array $a, array $b, array $c): int
    {
        $valor = ($b[0] - $a[0]) * ($c[1] - $b[1]) - ($b[1] - $a[1]) * ($c[0] - $b[0]);

        return abs($valor) < self::EPSILON ? 0 : ($valor > 0 ? 1 : -1);
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $punto
     * @param  array{0: float, 1: float}  $b
     */
    private static function enSegmento(array $a, array $punto, array $b): bool
    {
        return $punto[0] <= max($a[0], $b[0]) && $punto[0] >= min($a[0], $b[0])
            && $punto[1] <= max($a[1], $b[1]) && $punto[1] >= min($a[1], $b[1]);
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private static function mismoPunto(array $a, array $b): bool
    {
        return abs($a[0] - $b[0]) < self::EPSILON && abs($a[1] - $b[1]) < self::EPSILON;
    }
}
