<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Application\Zona\ZonaMapService;
use App\Application\Zona\ZonasFiltro;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\ProveedorZonaHistorialModel;
use App\Infrastructure\Persistence\Models\RutaDetalleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Database\Seeders\ZonaDelimitacionSeeder;
use Database\Seeders\ZonaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * Modal "Asignar proveedor a zona y ruta" con las cuatro zonas reales (ZonaSeeder +
 * ZonaDelimitacionSeeder): el modal recibe las mismas delimitaciones del mapa general y el
 * servidor vuelve a verificar, con esa geometría, que el punto marcado cae en la zona de la ruta.
 */
beforeEach(function () {
    $this->seed([ZonaSeeder::class, ZonaDelimitacionSeeder::class]);
    $this->admin = fixture_usuario('ADMINISTRADOR');
    $this->zonas = ZonaModel::pluck('id', 'nombre')->all();
    $this->rutas = collect($this->zonas)->map(fn (int $zonaId) => app(RegistrarRuta::class)->execute(zonaId: $zonaId, nombre: 'Ruta principal')->id())->all();
});

/** Un punto que está dentro de la delimitación de la zona (el de su etiqueta en el mapa). */
function punto_en(string $zona): array
{
    $etiqueta = collect(app(ZonaMapService::class)->obtener(ZonasFiltro::resolver(busqueda: null, estado: null))->zonas)
        ->firstWhere('nombre', $zona)['etiqueta'];

    return ['latitud' => round($etiqueta['lat'], 7), 'longitud' => round($etiqueta['lng'], 7)];
}

function guardar_ubicacion(ProveedorModel $proveedor, int $zonaId, int $rutaId, array $punto)
{
    return test()->actingAs(test()->admin)->putJson(
        route('admin.zonas.proveedores.asignacion', $proveedor->id),
        ['zona_id' => $zonaId, 'ruta_id' => $rutaId, ...$punto, 'referencia_ubicacion' => null],
    );
}

it('el modal recibe las cuatro zonas con el mismo id, nombre, color y delimitación del mapa general', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.zonas.index'))->assertOk();

    $catalogo = collect($response->viewData('catalogo'))->keyBy('nombre');
    $mapa = collect($response->viewData('mapaZonas')->zonas)->keyBy('nombre');

    expect($catalogo->keys()->all())->toBe(['Collana 1', 'Collana 2', 'Faon', 'Yasin']);

    foreach ($catalogo as $nombre => $zona) {
        expect($zona['id'])->toBe($this->zonas[$nombre])
            ->and($zona['color'])->toBe($mapa[$nombre]['color'])
            ->and($zona['geometry'])->toBe($mapa[$nombre]['geometry'])
            ->and($zona['geometry']['type'])->toBe('Polygon')
            ->and($zona['rutas'])->toBe([['id' => $this->rutas[$nombre], 'nombre' => 'Ruta principal']]);
    }
});

it('un proveedor cargado con zona pero sin ruta ni coordenadas se ofrece con su zona registrada y sin pin', function () {
    $proveedor = fixture_proveedor(ZonaModel::find($this->zonas['Collana 1']), '70000424');

    $this->actingAs($this->admin)->getJson(route('admin.zonas.proveedores.buscar', ['q' => '70000424']))
        ->assertOk()
        ->assertJsonPath('0.proveedorId', $proveedor->id)
        ->assertJsonPath('0.rutaId', null)
        ->assertJsonPath('0.latitud', null)
        ->assertJsonPath('0.longitud', null)
        ->assertJsonPath('0.zonaRegistradaId', $this->zonas['Collana 1'])
        ->assertJsonPath('0.zonaRegistradaNombre', 'Collana 1');
});

it('guarda la ubicación marcada dentro de la zona de la ruta y deja enfocado al proveedor en el mapa general', function () {
    $proveedor = fixture_proveedor(ZonaModel::find($this->zonas['Collana 1']));
    $punto = punto_en('Yasin');

    guardar_ubicacion($proveedor, $this->zonas['Yasin'], $this->rutas['Yasin'], $punto)
        ->assertOk()
        ->assertJsonPath('redirect', route('admin.zonas.index', ['zona' => $this->zonas['Yasin'], 'proveedor' => $proveedor->id]));

    $detalle = RutaDetalleModel::where('proveedor_id', $proveedor->id)->first();
    expect((float) $detalle->latitud)->toBe($punto['latitud'])
        ->and((float) $detalle->longitud)->toBe($punto['longitud'])
        ->and($detalle->ruta_id)->toBe($this->rutas['Yasin'])
        ->and($proveedor->fresh()->zona_id)->toBe($this->zonas['Yasin']);

    // El pin aparece en el mapa general, dentro de su delimitación.
    $pin = collect(app(ZonaMapService::class)->obtener(ZonasFiltro::resolver(busqueda: null, estado: null))->proveedores)->firstWhere('id', $proveedor->id);
    expect($pin['zonaId'])->toBe($this->zonas['Yasin'])->and($pin['fueraDeDelimitacion'])->toBeFalse();
});

it('el servidor rechaza un punto que está en otra zona aunque el navegador haya enviado esa ruta', function () {
    $proveedor = fixture_proveedor(ZonaModel::find($this->zonas['Faon']));

    guardar_ubicacion($proveedor, $this->zonas['Faon'], $this->rutas['Faon'], punto_en('Yasin'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['latitud' => 'La ubicación marcada está en Yasin: elige una ruta de Yasin o corrige el marcador.']);

    $this->assertDatabaseCount('ruta_detalles', 0);
});

it('rechaza una ubicación fuera de las cuatro zonas operativas', function () {
    $proveedor = fixture_proveedor(ZonaModel::find($this->zonas['Faon']));

    guardar_ubicacion($proveedor, $this->zonas['Faon'], $this->rutas['Faon'], ['latitud' => -16.5, 'longitud' => -68.1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['latitud' => 'La ubicación seleccionada no pertenece a ninguna zona operativa.']);

    $this->assertDatabaseCount('ruta_detalles', 0);
});

it('mover el punto de Collana 1 a Collana 2 cambia ruta y zona, y cierra el historial de zona anterior', function () {
    // Parte de otra zona para que la asignación a Collana 1 abra su historial, como en el flujo real.
    $proveedor = fixture_proveedor(ZonaModel::find($this->zonas['Faon']));
    app(AsignarProveedorARuta::class)->execute(...['rutaId' => $this->rutas['Collana 1'], 'proveedorId' => $proveedor->id, ...punto_en('Collana 1')]);

    guardar_ubicacion($proveedor, $this->zonas['Collana 2'], $this->rutas['Collana 2'], punto_en('Collana 2'))->assertOk();

    expect(RutaDetalleModel::where('proveedor_id', $proveedor->id)->value('ruta_id'))->toBe($this->rutas['Collana 2'])
        ->and($proveedor->fresh()->zona_id)->toBe($this->zonas['Collana 2'])
        ->and(ProveedorZonaHistorialModel::where('proveedor_id', $proveedor->id)->whereNull('fecha_fin')->value('zona_id'))->toBe($this->zonas['Collana 2'])
        ->and(ProveedorZonaHistorialModel::where('proveedor_id', $proveedor->id)->where('zona_id', $this->zonas['Collana 1'])->whereNotNull('fecha_fin')->exists())->toBeTrue();
});

it('un proveedor con coordenadas abre el modal con su pin real', function () {
    $proveedor = fixture_proveedor(ZonaModel::find($this->zonas['Faon']));
    $punto = punto_en('Faon');
    app(AsignarProveedorARuta::class)->execute(...['rutaId' => $this->rutas['Faon'], 'proveedorId' => $proveedor->id, ...$punto]);

    $this->actingAs($this->admin)->getJson(route('admin.zonas.proveedores.buscar', ['q' => $proveedor->dni]))
        ->assertOk()
        ->assertJsonPath('0.zonaId', $this->zonas['Faon'])
        ->assertJsonPath('0.latitud', $punto['latitud'])
        ->assertJsonPath('0.longitud', $punto['longitud']);
});

// ---------------------------------------------------- nombres equivalentes

it('no permite crear una zona con un nombre equivalente a una existente', function (string $nombre) {
    $this->actingAs($this->admin)->post(route('admin.zonas.store'), ['nombre' => $nombre])
        ->assertSessionHasErrors(['nombre' => 'Ya existe una zona equivalente con el nombre Collana 1.']);

    expect(ZonaModel::count())->toBe(4);
})->with(['Collana1', 'COLLANA 1', ' collana   1 ']);

it('no permite renombrar una zona con el nombre equivalente de otra, pero sí reescribir el suyo', function () {
    $this->actingAs($this->admin)->patch(route('admin.zonas.update', $this->zonas['Faon']), ['nombre' => 'Collana2'])
        ->assertSessionHasErrors(['nombre' => 'Ya existe una zona equivalente con el nombre Collana 2.']);

    $this->actingAs($this->admin)->patch(route('admin.zonas.update', $this->zonas['Faon']), ['nombre' => 'FAON'])
        ->assertSessionHasNoErrors();

    expect(ZonaModel::find($this->zonas['Faon'])->nombre)->toBe('FAON');
});
