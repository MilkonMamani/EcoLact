<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Application\Zona\Contracts\ZonaProveedoresQuery;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function proveedorNombrado(string $nombres, string $apellidos, string $dni): ProveedorModel
{
    $proveedor = fixture_proveedor(fixture_zona(), $dni);
    $proveedor->update(['nombres' => $nombres, 'apellidos' => $apellidos]);

    return $proveedor;
}

it('lista los proveedores de una zona por ruta y orden de visita, con zona, ruta y ubicación', function () {
    $zona = fixture_zona('Collana 1');
    $rutaB = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 02');
    $rutaA = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $p1 = fixture_proveedor($zona, '72100012');
    $p2 = fixture_proveedor($zona, '72100013');
    $p3 = fixture_proveedor($zona, '72100014');
    app(AsignarProveedorARuta::class)->execute(rutaId: $rutaA->id(), proveedorId: $p1->id, latitud: -15.8, longitud: -70.0);
    app(AsignarProveedorARuta::class)->execute(rutaId: $rutaA->id(), proveedorId: $p2->id);
    app(AsignarProveedorARuta::class)->execute(rutaId: $rutaB->id(), proveedorId: $p3->id);

    $filas = app(ZonaProveedoresQuery::class)->deZona($zona->id);

    expect(array_map(fn ($f) => $f->dni, $filas))->toBe(['72100012', '72100013', '72100014']);
    expect($filas[0]->zonaNombre)->toBe('Collana 1')
        ->and($filas[0]->rutaNombre)->toBe('Ruta 01')
        ->and($filas[0]->ordenVisita)->toBe(1)
        ->and($filas[0]->tieneUbicacion())->toBeTrue()
        ->and($filas[1]->ordenVisita)->toBe(2)
        ->and($filas[1]->tieneUbicacion())->toBeFalse()
        ->and($filas[2]->rutaNombre)->toBe('Ruta 02');
});

it('no mezcla proveedores de otras zonas', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $rutaB = app(RegistrarRuta::class)->execute(zonaId: $zonaB->id, nombre: 'Ruta B');
    $proveedor = fixture_proveedor($zonaB);
    app(AsignarProveedorARuta::class)->execute(rutaId: $rutaB->id(), proveedorId: $proveedor->id);

    expect(app(ZonaProveedoresQuery::class)->deZona($zonaA->id))->toBe([]);
});

it('lista y cuenta solo a los proveedores activos que no están en ninguna ruta', function () {
    $zona = fixture_zona();
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta 01');
    $asignado = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id(), proveedorId: $asignado->id);
    $libre = proveedorNombrado('Wilson', 'Poma', '72100013');
    $inactivo = proveedorNombrado('Inactivo', 'Perez', '72100099');
    $inactivo->update(['estado' => 'INACTIVO']);

    $query = app(ZonaProveedoresQuery::class);

    expect(array_map(fn ($f) => $f->dni, $query->sinAsignar()))->toBe(['72100013']);
    expect($query->contarSinAsignar())->toBe(1);
    expect($query->sinAsignar()[0]->estaAsignado())->toBeFalse();
});

it('busca proveedores por nombre, apellido, nombre completo o DNI', function (string $texto, array $dnisEsperados) {
    proveedorNombrado('Sandra', 'Ccama Quispe', '72100012');
    proveedorNombrado('Wilson', 'Poma Mamani', '72100013');
    proveedorNombrado('Ana', 'Quispe Huanca', '45678912');

    $dnis = array_map(fn ($f) => $f->dni, app(ZonaProveedoresQuery::class)->buscar($texto));

    expect($dnis)->toEqualCanonicalizing($dnisEsperados);
})->with([
    'por nombre' => ['sandra', ['72100012']],
    'por apellido compartido' => ['quispe', ['72100012', '45678912']],
    'por nombre completo' => ['Ana Quispe', ['45678912']],
    'por DNI' => ['721000', ['72100012', '72100013']],
    'sin coincidencias' => ['zzz', []],
    'vacío' => ['   ', []],
]);

it('la búsqueda excluye proveedores inactivos y trata % y _ como texto literal', function () {
    proveedorNombrado('Sandra', 'Ccama', '72100012');
    $inactivo = proveedorNombrado('Sandro', 'Inactivo', '72100055');
    $inactivo->update(['estado' => 'INACTIVO']);

    $query = app(ZonaProveedoresQuery::class);

    expect(array_map(fn ($f) => $f->dni, $query->buscar('sand')))->toBe(['72100012']);
    expect($query->buscar('%'))->toBe([]);
});
