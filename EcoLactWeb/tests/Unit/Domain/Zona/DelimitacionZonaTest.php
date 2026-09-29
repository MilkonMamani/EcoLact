<?php

use App\Domain\Zona\DelimitacionZona;

/** Cuadrado de 0.1° alrededor de Puno, en [latitud, longitud]. */
function cuadradoPuno(): array
{
    return [[-15.80, -70.05], [-15.80, -69.95], [-15.90, -69.95], [-15.90, -70.05]];
}

it('cierra el anillo y guarda la geometría como GeoJSON Polygon en orden [longitud, latitud]', function () {
    $geojson = DelimitacionZona::desdeVertices(cuadradoPuno())->aGeoJson();

    expect($geojson['type'])->toBe('Polygon')
        ->and($geojson['coordinates'][0])->toHaveCount(5)
        ->and($geojson['coordinates'][0][0])->toBe([-70.05, -15.80])
        ->and($geojson['coordinates'][0][4])->toBe($geojson['coordinates'][0][0]);
});

it('lee su propio GeoJSON (también dentro de un Feature) sin perder puntos', function () {
    $geojson = DelimitacionZona::desdeVertices(cuadradoPuno())->aGeoJson();

    expect(DelimitacionZona::desdeGeoJson(json_encode($geojson))->vertices)->toBe(cuadradoPuno())
        ->and(DelimitacionZona::desdeGeoJson(['type' => 'Feature', 'geometry' => $geojson])->vertices)->toBe(cuadradoPuno());
});

it('rechaza una delimitación con menos de 3 puntos distintos', function () {
    DelimitacionZona::desdeVertices([[-15.8, -70.0], [-15.9, -70.0], [-15.8, -70.0]]);
})->throws(InvalidArgumentException::class, 'al menos 3 puntos distintos');

it('rechaza un polígono cuyos lados se cruzan', function () {
    DelimitacionZona::desdeVertices([[-15.80, -70.05], [-15.90, -69.95], [-15.80, -69.95], [-15.90, -70.05]]);
})->throws(InvalidArgumentException::class, 'sus lados se cruzan');

it('rechaza puntos en línea recta, coordenadas fuera de rango y JSON inválido', function (mixed $entrada, string $mensaje) {
    expect(fn () => is_array($entrada) && array_is_list($entrada) ? DelimitacionZona::desdeVertices($entrada) : DelimitacionZona::desdeGeoJson($entrada))
        ->toThrow(InvalidArgumentException::class, $mensaje);
})->with([
    'colineales' => [[[-15.8, -70.0], [-15.85, -70.0], [-15.9, -70.0]], 'línea recta'],
    'fuera de rango' => [[[-95, -70.0], [-15.85, -70.1], [-15.9, -70.0]], 'fuera de rango'],
    'json roto' => ['{"type":"Polygon"', 'JSON válido'],
    'otro tipo' => [['type' => 'Point', 'coordinates' => [-70.0, -15.8]], 'tipo Polygon'],
]);

it('sabe si un punto está dentro de la delimitación', function () {
    $delimitacion = DelimitacionZona::desdeVertices(cuadradoPuno());

    expect($delimitacion->contiene(-15.85, -70.0))->toBeTrue()
        ->and($delimitacion->contiene(-15.95, -70.0))->toBeFalse();
});

it('ubica la etiqueta en el centroide, o dentro del polígono cuando es cóncavo', function () {
    $centro = DelimitacionZona::desdeVertices(cuadradoPuno())->puntoEtiqueta();

    expect($centro['lat'])->toEqualWithDelta(-15.85, 1e-9)
        ->and($centro['lng'])->toEqualWithDelta(-70.0, 1e-9);

    // Una "U": su centroide cae en el hueco, fuera del polígono.
    $u = DelimitacionZona::desdeVertices([[0, 0], [0, 3], [3, 3], [3, 2], [1, 2], [1, 1], [3, 1], [3, 0]]);
    $etiqueta = $u->puntoEtiqueta();

    expect($u->contiene($etiqueta['lat'], $etiqueta['lng']))->toBeTrue();
});
